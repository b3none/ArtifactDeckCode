<?php

namespace ValveSoftware\ArtifactDeckCode;

use ValveSoftware\ArtifactDeckCode\Processors\ArtifactDeckDecoder;
use ValveSoftware\ArtifactDeckCode\Processors\ArtifactDeckEncoder;

class Client
{
    /**
     * @var ArtifactDeckDecoder
     */
    protected $artifactDeckDecoder;

    /**
     * @var ArtifactDeckEncoder
     */
    protected $artifactDeckEncoder;

    public static function create()
    {
        return new self(new ArtifactDeckDecoder(), new ArtifactDeckEncoder());
    }

    /**
     * Client constructor.
     * @param ArtifactDeckDecoder $artifactDeckDecoder
     * @param ArtifactDeckEncoder $artifactDeckEncoder
     */
    public function __construct(ArtifactDeckDecoder $artifactDeckDecoder, ArtifactDeckEncoder $artifactDeckEncoder)
    {
        $this->artifactDeckDecoder = $artifactDeckDecoder;
        $this->artifactDeckEncoder = $artifactDeckEncoder;
    }

    /**
     * Parse deck code
     *
     * @param string $deckCode
     * @return array|bool
     */
    public function parseDeck(string $deckCode)
    {
        return $this->artifactDeckDecoder->ParseDeck($deckCode);
    }

    public function encodeDeck($deckContents)
    {
        return $this->artifactDeckEncoder->encodeDeck($deckContents);
    }
}