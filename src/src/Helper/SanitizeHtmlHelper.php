<?php

namespace App\Helper;

class SanitizeHtmlHelper
{
    /**
     * Perform basic sanitization on the raw HTML of a Post or Comment to parse
     * Reddit's HTML and clean up extraneous tags.
     *
     * @param  string|null  $html
     *
     * @return string
     */
    public function sanitizeHtml(?string $html): string
    {
        // Initial empty check to avoid deprecation notice regarding passing a
        // null to the `trim` function in the next step.
        if (empty($html) || ctype_space($html)) {
            return '';
        }

        // Trim leading and trailing whitespace.
        $html = trim($html);

        // Run a double decode through Reddit's Markdown-converted HTML.
        $html = html_entity_decode($html);
        $html = html_entity_decode($html);

        // Clean up unneeded tags and strings.
        $stringsToRemove = [
            '<!-- SC_OFF -->',
            '<!-- SC_ON -->',
            '\n',
        ];
        $html = str_replace($stringsToRemove, '', $html);

        return $html;
    }
}
