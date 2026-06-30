<?php

namespace App\Domain\Target\Template\Excel;

/**
 * Row header
 */
class RowHead extends Node
{
    use NodeParser;

    // Number of rows associated with the node; only valid for leaf nodes
    private $rowCount;

    public function __construct(string $name = '', string $title = '', Style $style = null, int $rowCount = 1)
    {
        $this->name = $name;
        $this->title = $title;
        $this->style = $style;
        $this->rowCount = $rowCount;
    }

    /**
     * Number of rows associated with a leaf node
     */
    public function rowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Override breadth detection logic: for a node without children, add row_count (the number of rows associated with this node) to the breadth
     */
    protected function detectBreadth(Node $node, int &$breadth = 0): int
    {
        if ($node->isLeaf()) {
            if ($node instanceof RowHead) {
                $breadth += $node->rowCount() ?: 1;
            }
            return $breadth;
        }

        foreach ($node->children() as $childNode) {
            $this->detectBreadth($childNode, $breadth);
        }

        return $breadth;
    }

    protected static function createNode(array $rowCfg): Node
    {
        $styleCfg = $rowCfg['style'] ?? ['bg_color' => $rowCfg['bg_color'] ?? ''];
        $style = new Style($styleCfg);
        return new RowHead($rowCfg['name'] ?? '', $rowCfg['title'] ?? '', $style, $rowCfg['row_count'] ?? 0);
    }
}
