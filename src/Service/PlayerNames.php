<?php

namespace App\Service;

/**
 * Players never type a name: the server gives them one from these lists.
 * Draft lists for the PoC; the final ones still have to be written.
 */
final class PlayerNames
{
    private const ADJECTIVES = [
        'Snelle', 'Glijdende', 'Dappere', 'Vrolijke', 'Wilde', 'Slimme', 'Koude',
        'Bruisende', 'Stoere', 'Lenige', 'Rappe', 'Gezellige', 'Fonkelende', 'Zwierige',
    ];

    private const NOUNS = [
        'Rendier', 'Kerstbal', 'Sneeuwpop', 'Pinguïn', 'Poolvos', 'Kerstelf', 'Ijsbeer',
        'Slee', 'Sneeuwvlok', 'Peperkoek', 'Arreslee', 'Zuurstok', 'Marmot', 'Skileraar',
    ];

    public function random(): string
    {
        $adjective = self::ADJECTIVES[random_int(0, \count(self::ADJECTIVES) - 1)];
        $noun = self::NOUNS[random_int(0, \count(self::NOUNS) - 1)];

        return \sprintf('%s %s %d', $adjective, $noun, random_int(1, 99));
    }
}
