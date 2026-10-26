<?php

namespace App\Enums;

/**
 * What sits above a template's text: nothing, a short line (which may hold
 * one variable), or a picture, document or video chosen when sending.
 */
enum TemplateHeader: string
{
    case None = 'none';
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';

    public function hasMedia(): bool
    {
        return in_array($this, [self::Image, self::Document, self::Video], true);
    }
}
