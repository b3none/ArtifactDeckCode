<?php

namespace ValveSoftware\ArtifactDeckCode\Processors;

class ArtifactDeckDecoder
{
	const CURRENT_VERSION = 2;

    /**
     * @var string
     */
	private $sm_rgchEncodedPrefix = "ADC";

	// returns ["heroes" => [id, turn], "cards" => [id, count], "name" => name]

    /**
     * @param string $deckCode
     * @return array|null
     */
	public function parseDeck(string $deckCode): ?array
	{
		$deckBytes = $this->DecodeDeckString($deckCode);

		if (!$deckBytes) {
            return null;
        }

		$deck = $this->ParseDeckInternal($deckBytes);

		return is_array($deck) ? $deck : null;
	}

    /**
     * @param string $deckCode
     * @return array|null
     */
	public function getRawDeckBytes(string $deckCode): ?array
	{
		return $this->DecodeDeckString($deckCode);
	}

	private function DecodeDeckString(string $deckCode)
	{
		//check for prefix
		if (substr($deckCode, 0, strlen($this->sm_rgchEncodedPrefix)) != $this->sm_rgchEncodedPrefix) {
            return null;
        }

		//strip prefix from deck code
		$strNoPrefix = substr($deckCode, strlen($this->sm_rgchEncodedPrefix));

		// deck strings are base64 but with url compatible strings, put the URL special chars back
		$strNoPrefix = str_replace(['-', '_'], ['/', '='], $strNoPrefix);
		$decoded = base64_decode($strNoPrefix);

		return unpack("C*", $decoded);
	}

	//reads out a var-int encoded block of bits, returns true if another chunk should follow
	private function ReadBitsChunk($nChunk, $nNumBits, $nCurrShift, &$nOutBits)
	{
		$nContinueBit = (1 << $nNumBits);
		$nNewBits = $nChunk & ($nContinueBit - 1);
		$nOutBits |= ($nNewBits << $nCurrShift);

		return ($nChunk & $nContinueBit) != 0;
	}

	private function ReadVarEncodedUint32($nBaseValue, $nBaseBits, $data, &$indexStart, $indexEnd, &$outValue): bool
	{
		$outValue = 0;

		$nDeltaShift = 0;
		if (($nBaseBits == 0) || $this->ReadBitsChunk($nBaseValue, $nBaseBits, $nDeltaShift, $outValue)) {
			$nDeltaShift += $nBaseBits;

			while (1) {
				//do we have more room?
				if ($indexStart > $indexEnd) {
                    return null;
                }

				//read the bits from this next byte and see if we are done
				$nNextByte = $data[$indexStart++];
				if (!$this->ReadBitsChunk($nNextByte, 7, $nDeltaShift, $outValue)) {
                    break;
                }

				$nDeltaShift += 7;
			}
		}

		return true;
	}

	//handles decoding a card that was serialized
	private function ReadSerializedCard( $data, &$indexStart, $indexEnd, &$nPrevCardBase, &$nOutCount, &$nOutCardID )
	{
		//end of the memory block?
		if ($indexStart > $indexEnd) {
            return null;
        }

		//header contains the count (2 bits), a continue flag, and 5 bits of offset data. If we have 11 for the count bits we have the count
		//encoded after the offset
		$nHeader = $data[$indexStart++];
		$bHasExtendedCount = (($nHeader >> 6) == 0x03);

		//read in the delta, which has 5 bits in the header, then additional bytes while the value is set
		$nCardDelta = 0;
		if (!$this->ReadVarEncodedUint32( $nHeader, 5, $data, $indexStart, $indexEnd, $nCardDelta)) {
            return null;
        }

		$nOutCardID = $nPrevCardBase + $nCardDelta;

		//now parse the count if we have an extended count
		if ($bHasExtendedCount) {
			if (!$this->ReadVarEncodedUint32(0, 0, $data, $indexStart, $indexEnd, $nOutCount)) {
                return null;
            }
		} else {
			//the count is just the upper two bits + 1 (since we don't encode zero)
			$nOutCount = ($nHeader >> 6) + 1;
		}

		//update our previous card before we do the remap, since it was encoded without the remap
		$nPrevCardBase = $nOutCardID;
		return true;
	}

	// $deckBytes will be 1 indexed (due to unpack return value).  If you are using 0 based indexing
	//	for your byte array, be sure to adjust appropriate below (see // 1 indexed)
	private function ParseDeckInternal($deckBytes)
	{
		$nCurrentByteIndex = 1;
		$nTotalBytes = count($deckBytes);

		//check version num
		$nVersionAndHeroes = $deckBytes[$nCurrentByteIndex++];
		$version = $nVersionAndHeroes >> 4;
		if (self::CURRENT_VERSION != $version && $version != 1) {
            return null;
        }

		//do checksum check
		$nChecksum = $deckBytes[$nCurrentByteIndex++];

		$nStringLength = 0;
		if ($version > 1) {
            $nStringLength = $deckBytes[$nCurrentByteIndex++];
        }

		$nTotalCardBytes = $nTotalBytes - $nStringLength;

		//grab the string size
        $nComputedChecksum = 0;
        for ($i = $nCurrentByteIndex; $i <= $nTotalCardBytes; $i++) {
            $nComputedChecksum += $deckBytes[$i];
        }

        $masked = ($nComputedChecksum & 0xFF);
        if ($nChecksum != $masked) {
            return null;
        }

		//read in our hero count (part of the bits are in the version, but we can overflow bits here
		$nNumHeroes = 0;
		if (!$this->ReadVarEncodedUint32($nVersionAndHeroes, 3, $deckBytes, $nCurrentByteIndex, $nTotalCardBytes, $nNumHeroes)) {
            return null;
        }

		//now read in the heroes
		$heroes = [];
        $nPrevCardBase = 0;
        for ($nCurrHero = 0; $nCurrHero < $nNumHeroes; $nCurrHero++) {
            $nHeroTurn = 0;
            $nHeroCardID = 0;
            if (!$this->ReadSerializedCard($deckBytes, $nCurrentByteIndex, $nTotalCardBytes, $nPrevCardBase, $nHeroTurn, $nHeroCardID)) {
                return null;
            }

            array_push( $heroes, ["id" => $nHeroCardID, "turn" => $nHeroTurn]);
        }

		$cards = [];
		$nPrevCardBase = 0;
		// 1 indexed - change to $nCurrentByteIndex < $nTotalCardBytes if 0 indexed
		while ($nCurrentByteIndex <= $nTotalCardBytes) {
			$nCardCount = 0;
			$nCardID = 0;
			if (!$this->ReadSerializedCard($deckBytes, $nCurrentByteIndex, $nTotalBytes, $nPrevCardBase, $nCardCount, $nCardID)) {
                return null;
            }

			array_push( $cards, ["id" => $nCardID, "count" => $nCardCount]);
		}

		$name = "";
		if ($nCurrentByteIndex <= $nTotalBytes) {
			$bytes = array_slice($deckBytes, -1 * $nStringLength);
			$name = implode(array_map("chr", $bytes));

			// replace strip_tags with an HTML sanitizer or escaper as needed.
			$name = strip_tags($name);
		}

		return ["heroes" => $heroes, "cards" => $cards, "name" => $name];
	}
};
