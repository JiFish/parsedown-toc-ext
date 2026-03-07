# Parsedown TOC Extension

A simple, lightweight, and modern Table of Contents extension for Parsedown and ParsedownExtra.
MIT licenced.

Provides:

* Markdown/HTML TOC generation
* Automatic slugified heading IDs
* Programmatic TOC access
* Configurable depth, numbering, and hierarchy behaviour
* Works with ParsedownExtra
* Manual TOC adjustment

This extension works with both [original parsedown](https://github.com/erusev/parsedown)
and the [community fork of Parsedown](https://github.com/parsedown/parsedown).

Inspired by Benjamin Hoegh's [ParsedownToc](https://github.com/BenjaminHoegh/ParsedownToc).

## Installation

```bash
composer require jifish/parsedown-toc-ext
```

If you want Parsedown Extra support:

```bash
composer require erusev/parsedown-extra
```

If you want the community fork of Parsedown, install it first.

```bash
composer require parsedown/parsedown
```

## Basic Usage

### Parsedown

```php
use jifish\ParsedownTocExt\ParsedownToc;

$parser = new ParsedownToc();

$html = $parser->text($markdown);
$toc = $parser->getToc();
$tocText = $parser->renderToc();
```

### Parsedown Extra

```php
use jifish\ParsedownTocExt\ParsedownExtraToc;

$parser = new ParsedownExtraToc();
$html = $parser->text($markdown);
$toc = $parser->getToc();
```

### Substitution of [TOC] marker
```php
use jifish\ParsedownTocExt\ParsedownToc;

$parser = new ParsedownToc();

$html = $parser->text($markdown);
$html = str_replace("[TOC]", $parser->renderToc(), $html);
```

## Heading ID Generation

Heading IDs are:

* Lowercased
* Non-alphanumeric characters removed
* Whitespace converted to hyphens
* Guaranteed unique per document, duplicate headings receive numeric suffixes

Example:

```markdown
### My Heading!
```

Becomes:

```html
<h3 id="my-heading">My Heading!</h3>
```


# Interfaces

### Basic Usage

#### `text(string $markdown): string`

As with Parsedown, but adds heading IDs, and builds and stores a new TOC structure.

#### `getToc(): array`

Returns the internal TOC structure.

Format:

```php
[
    'heading-id' => [
        'level' => 2,
        'text'  => 'Heading Text',
    ],
]
```

* Indexed by generated ID.
* Ordered in document order.
* `level` is the original heading level (`1-6`).

#### `getTocCount(): int`

Returns the number of headings in the TOC.

#### `removeHeading(string $id): void`

Removes a heading from the TOC by its ID.


#### `renderToc(...)`

Generates a Markdown-formatted TOC.

```php
renderToc(
    bool $asHtml = false,
    bool $collapseSkippedLevels = false,
    ?int $maxDepth = null,
    bool $numbered = false,
    bool $excludeFirstHeading = false
): string
```

**Parameters:**

* `$asHtml` (default `false`)

  If `true`, the generated Markdown TOC is returned as rendered HTML.

  Example:

  ```php
  echo $parser->renderToc(asHtml: true);
  ```

  Returns:
  
  ```html
  <ul>
      <li><a href="#title">Title</a></li>
      ...
  </ul>
  ```

* `$collapseSkippedLevels` (default `false`)
  
  Controls how heading level jumps are handled. When enabled, large jumps only increase nesting by one level.
  
  Example:
  
  ```markdown
  # A
  ### B
  ## C
  ```
  
  `false` (true depth):

  ```markdown
  - [A](#a)
          - [B](#b)
      - [C](#c)
  ```

  `true` (collapsed hierarchy):

  ```markdown
  - [A](#a)
      - [B](#b)
      - [C](#c)
  ```
* `$maxDepth` (default: `null`)

  Limits how deep headings are included.

  Depth is calculated relative to the lowest heading level in the document.


* `$numbered` (default: `false`)

  Uses ordered list syntax instead of bullet lists. Each nesting level maintains its own numbering sequence.

  Example:

  ```markdown
  1. [Title](#title)
      1. [Install](#install)
      2. [Usage](#usage)
  ```

* `$excludeFirstHeading` (default: `false`)

  Skips the first heading in the document. Useful when the first heading is the page title and should not appear in the TOC.

#### `hasToc(): bool`

Returns whether the internal TOC currently contains any entries.

### Customising TOC

#### `appendHeading(int $level, string $text, ?string $id = null): string`

Manually adds an entry to the end of the internal TOC structure, and returns its id.

This allows you to add headings that are not present in the Markdown source, for use
when combining parsed headings with dynamic or generated content, or to generate TOCs
without any source markdown.

Note: Calling `text()` will clear the internal TOC.

**Parameters**

* `$level`
  Heading level (`1-6`).
  A `ValueError` is thrown if the level is outside this range.

* `$text`
  The display text used in the TOC.

* `$id` (optional)
  Custom ID to use instead of generating one from `$text`.

  If omitted, the ID is generated from `$text` using the same slug rules as automatic headings.

  The final ID is always passed through the internal uniqueness check, so collisions are automatically resolved.

Example:

```php
$parser = new ParsedownToc();

$parser->addHeader(2, 'Installation');
$parser->addHeader(3, 'Windows');
$parser->addHeader(3, 'Linux');

echo $parser->getTocText();
```

Output:

```markdown
- [Installation](#installation)
    - [Windows](#windows)
    - [Linux](#linux)
```

#### `prependHeading(int $level, string $text, ?string $id = null): string`

As above, but adds the heading to the top instead.

#### `insertHeadingAfter(string $afterId, int $level, string $text, ?string $id = null): string`

As above, but inserts the heading directly below `$afterId`. Throws a **ValueError** if
the id is not found.
