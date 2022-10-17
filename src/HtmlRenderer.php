<?php

declare(strict_types=1);

namespace Termwind;

use DOMDocument;
use DOMNode;
use Termwind\Html\CodeRenderer;
use Termwind\Html\PreRenderer;
use Termwind\Html\TableRenderer;
use Termwind\ValueObjects\Node;

/**
 * @internal
 */
final class HtmlRenderer
{
    /**
     * Renders the given html.
     */
    public function render(string $html): void
    {
        $this->parse($html)->render();
    }

    /**
     * Parses the given html.
     */
    public function parse(string $html, array $mouse = [0, 0]): Components\Element
    {
        $dom = new DOMDocument();

        if (strip_tags($html) === $html) {
            return Termwind::span($html);
        }

        $html = '<?xml encoding="UTF-8">'.trim($html);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_COMPACT | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS | LIBXML_NOXMLDECL);

        /** @var DOMNode $body */
        $body = $dom->getElementsByTagName('body')->item(0);
        $el = $this->convert(new Node($body), $mouse);

        // @codeCoverageIgnoreStart
        return is_string($el)
            ? Termwind::span($el)
            : $el;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Convert a tree of DOM nodes to a tree of termwind elements.
     */
    private function convert(Node $node, array $mouse): Components\Element|string
    {
        $children = [];

        if ($node->isName('table')) {
            return (new TableRenderer)->toElement($node);
        } elseif ($node->isName('code')) {
            return (new CodeRenderer)->toElement($node);
        } elseif ($node->isName('pre')) {
            return (new PreRenderer)->toElement($node);
        }

        foreach ($node->getChildNodes() as $child) {
            $children[] = $this->convert($child, $mouse);
        }

        $children = array_filter($children, fn ($child) => $child !== '');

        return $this->toElement($node, $children, $mouse);
    }

    /**
     * Convert a given DOM node to it's termwind element equivalent.
     *
     * @param  array<int, Components\Element|string>  $children
     */
    private function toElement(Node $node, array $children, array $mouse): Components\Element|string
    {
        if ($node->isText() || $node->isComment()) {
            return (string) $node;
        }

        /** @var array<string, mixed> $properties */
        $properties = [
            'isFirstChild' => $node->isFirstChild(),
        ];

        $styles = $node->getClassAttribute();

        return match ($node->getName()) {
            'body' => $children[0], // Pick only the first element from the body node
            'div' => Termwind::div($children, $styles, $properties, $mouse),
            'p' => Termwind::paragraph($children, $styles, $properties, $mouse),
            'ul' => Termwind::ul($children, $styles, $properties, $mouse),
            'ol' => Termwind::ol($children, $styles, $properties, $mouse),
            'li' => Termwind::li($children, $styles, $properties, $mouse),
            'dl' => Termwind::dl($children, $styles, $properties, $mouse),
            'dt' => Termwind::dt($children, $styles, $properties, $mouse),
            'dd' => Termwind::dd($children, $styles, $properties, $mouse),
            'span' => Termwind::span($children, $styles, $properties, $mouse),
            'br' => Termwind::breakLine($styles, $properties, $mouse),
            'strong' => Termwind::span($children, $styles, $properties, $mouse)->strong(),
            'b' => Termwind::span($children, $styles, $properties, $mouse)->fontBold(),
            'em', 'i' => Termwind::span($children, $styles, $properties, $mouse)->italic(),
            'u' => Termwind::span($children, $styles, $properties, $mouse)->underline(),
            's' => Termwind::span($children, $styles, $properties, $mouse)->lineThrough(),
            'a' => Termwind::anchor($children, $styles, $properties, $mouse)->href($node->getAttribute('href')),
            'hr' => Termwind::hr($styles, $properties, $mouse),
            default => Termwind::div($children, $styles, $properties, $mouse),
        };
    }
}
