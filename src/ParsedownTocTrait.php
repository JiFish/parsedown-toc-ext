<?php

namespace jifish\ParsedownTocExt;

trait ParsedownTocTrait
{
    protected array $toc = [];
    protected array $slugCounts = [];

    /**
     * Parses Markdown text and resets the internal TOC state.
     *
     * @param string $text Markdown input.
     * @return string Rendered HTML output from Parsedown.
     */
    public function text($text)
    {
        $this->toc = [];
        $this->slugCounts = [];

        return parent::text($text);
    }

    protected function blockHeader($Line)
    {
        $block = parent::blockHeader($Line);

        if (
            !isset($block['element']['name']) ||
            !preg_match('/^h([1-6])$/', $block['element']['name'], $matches)
        ) {
            return $block;
        }

        $level = (int)$matches[1];
        $text  = $block['element']['handler']['argument'] ?? "";

        $id = $this->appendHeading($level, $text);

        $block['element']['attributes']['id'] = $id;

        return $block;
    }

    protected function generateUniqueSlug(string $text): string
    {
        $slug = $this->slugify($text);

        if (!isset($this->slugCounts[$slug])) {
            $this->slugCounts[$slug] = 0;
            return $slug;
        }

        $this->slugCounts[$slug]++;
        return $slug . '-' . $this->slugCounts[$slug];
    }

    protected function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/\s+/', '-', trim($text));

        return $text ?: 'section';
    }

    private function buildHeading(int $level, string $text, ?string $id = null): array
    {
        if ($level < 1 || $level > 6) {
            throw new \ValueError("Invalid level: $level. Level must be between 1 and 6, inclusive.");
        }

        if ($id === null) {
            $id = trim(strip_tags(parent::line($text)));
            $id = $id ?: 'unnamed-heading';
        }

        $id = $this->generateUniqueSlug($id);
        $value = [
            'level' => $level,
            'text'  => $text,
        ];

        return [$id, $value];
    }

    /**
     * Inserts a heading into the TOC directly after the specified heading ID.
     *
     * @param string $afterId Existing heading ID to insert after.
     * @param int $level Heading level (1-6).
     * @param string $text Heading text displayed in the TOC.
     * @param string|null $id Optional custom ID for the heading.
     * @return string The generated or resolved unique heading ID.
     * @throws \ValueError If the specified ID does not exist.
     */
    public function insertHeadingAfter(string $afterId, int $level, string $text, ?string $id = null): string
    {
        $pos = array_search($afterId, array_keys($this->toc), true);

        if ($pos === false) {
            throw new \ValueError("TOC id $afterId, not found. Can't insert above.");
        }

        [$id, $value] = $this->buildHeading($level, $text, $id);
        $pos++;
        $this->toc = array_slice($this->toc, 0, $pos, true)
            + [$id => $value]
            + array_slice($this->toc, $pos, null, true);
        return $id;
    }

    /**
     * Appends a heading to the end of the TOC.
     *
     * @param int $level Heading level (1-6).
     * @param string $text Heading text displayed in the TOC.
     * @param string|null $id Optional custom ID for the heading.
     * @return string The generated or resolved unique heading ID.
     */
    public function appendHeading(int $level, string $text, ?string $id = null): string
    {
        [$id, $value] = $this->buildHeading($level, $text, $id);
        $this->toc[$id] = $value;
        return $id;
    }

    /**
     * Prepends a heading to the beginning of the TOC.
     *
     * @param int $level Heading level (1-6).
     * @param string $text Heading text displayed in the TOC.
     * @param string|null $id Optional custom ID for the heading.
     * @return string The generated or resolved unique heading ID.
     */
    public function prependHeading(int $level, string $text, ?string $id = null): string
    {
        [$id, $value] = $this->buildHeading($level, $text, $id);
        $this->toc = [$id => $value] + $this->toc;
        return $id;
    }

    /**
     * Removes a heading from the TOC by its ID.
     *
     * @param string $id Heading ID to remove.
     * @return void
     */
    public function removeHeading(string $id): void
    {
        unset($this->toc[$id]);
    }

    /**
     * Checks whether the TOC currently contains any headings.
     *
     * @return bool True if the TOC contains entries, otherwise false.
     */
    public function hasToc(): bool
    {
        return !empty($this->toc);
    }

    /**
     * Returns the internal TOC structure.
     *
     * @return array<string,array{level:int,text:string}> Ordered heading list indexed by ID.
     */
    public function getToc(): array
    {
        return $this->toc;
    }

    /**
     * Returns the number of headings currently in the TOC.
     *
     * @return int Number of TOC entries.
     */
    public function getTocCount(): int
    {
        return count($this->toc);
    }

    /**
     * Generates a Markdown or HTML table of contents from the collected headings.
     *
     * @param bool $asHtml If true, returns rendered HTML instead of Markdown.
     * @param bool $collapseSkippedLevels Whether large heading level jumps collapse to a single nesting level.
     * @param int|null $maxDepth Maximum relative depth to include.
     * @param bool $numbered Use ordered list numbering instead of bullet points.
     * @param bool $excludeFirstHeading Whether to omit the first heading in the document.
     * @return string Generated TOC as Markdown or HTML.
     */
    public function renderToc(
        bool $asHtml = false,
        bool $collapseSkippedLevels = false,
        ?int $maxDepth = null,
        bool $numbered = false,
        bool $excludeFirstHeading = false
    ): string
    {
        if (empty($this->toc)) {
            return '';
        }

        $entries = array_values($this->toc);
        $minLevel = min(array_column($entries, 'level'));

        $lines = [];
        $previousDepth = 0;
        $isFirst = true;
        $counters = [];

        foreach ($this->toc as $entryId => $entry) {

            if ($excludeFirstHeading && $isFirst) {
                $isFirst = false;
                continue;
            }

            $isFirst = false;

            $depth = $entry['level'] - $minLevel;

            if ($collapseSkippedLevels && $depth > $previousDepth + 1) {
                $depth = $previousDepth + 1;
            }

            if ($maxDepth !== null && $depth >= $maxDepth) {
                continue;
            }

            $indent = str_repeat('    ', max(0, $depth));

            if ($numbered) {
                foreach ($counters as $d => $_) {
                    if ($d > $depth) {
                        unset($counters[$d]);
                    }
                }

                $counters[$depth] = ($counters[$depth] ?? 0) + 1;
                $prefix = $counters[$depth] . '.';
            } else {
                $prefix = '-';
            }

            $lines[] = sprintf(
                '%s%s [%s](#%s)',
                $indent,
                $prefix,
                $entry['text'],
                $entryId
            );

            $previousDepth = $depth;
        }

        $markdown = implode("\n", $lines);

        return $asHtml ? parent::text($markdown) : $markdown;
    }
}
