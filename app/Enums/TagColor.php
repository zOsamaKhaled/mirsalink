<?php

namespace App\Enums;

/**
 * The seven colours a tag is drawn in, matching the tag-* tokens of the
 * design system. Rose is not one of them: it belongs to the handover.
 */
enum TagColor: string
{
    case Blue = 'blue';
    case Violet = 'violet';
    case Cyan = 'cyan';
    case Amber = 'amber';
    case Slate = 'slate';
    case Red = 'red';
    case Brown = 'brown';

    /**
     * The colour a new tag gets when no one picked one, taking turns so
     * imported tags don't all come out blue.
     */
    public static function forPosition(int $position): self
    {
        $cases = self::cases();

        return $cases[$position % count($cases)];
    }
}
