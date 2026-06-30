<?php

namespace App\Domain\Target\Template\Excel;

/**
 * Table node
 */
class Node
{
    public const NODE_TOP = '_top_';
    
    protected $name;
    protected $title;
    /**
     * Absolute position of the node in the tree: [row (depth), column (breadth)], indexed from 0
     */
    protected $pos = [0, 0];
    /**
     * @var Style
     */
    protected $style;
    /**
     * @var array
     */
    protected $children = [];

    public function __construct(string $name = '', string $title = '', Style $style = null)
    {
        $this->name = $name;
        $this->title = $title;
        $this->style = $style;
    }

    public function appendChild(Node $node)
    {
        $this->children[] = $node;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * Whether this is a leaf node
     */
    public function isLeaf(): bool
    {
        return boolval(!count($this->children));
    }

    public function style(): Style
    {
        return $this->style;
    }

    public function children(): array
    {
        return $this->children;
    }

    public function setPosition($row, $col)
    {
        $this->pos = [$row, $col];
    }

    /**
     * Get the node's position in the tree
     */
    public function getPosition(): array
    {
        return $this->pos;
    }

    /**
     * Tree depth
     */
    public function deep(): int
    {
        return $this->detectDeep($this);
    }

    /**
     * Tree breadth
     */
    public function breadth(): int
    {
        return $this->detectBreadth($this);
    }

    /**
     * Search for a node by name
     */
    public function search(string $name): ?Node
    {
        return $this->searchNode($name, $this);
    }

    /**
     * Get all leaf nodes of a given node
     * @param Node $node
     * @return Node[] Array of leaf nodes
     */
    public static function fetchAllLeaves(Node $node): array
    {
        if ($node->isLeaf()) {
            return [$node];
        }

        $arr = [];
        foreach ($node->children() as $cNode) {
            $arr = array_merge($arr, self::fetchAllLeaves($cNode));
        }

        return $arr;
    }

    protected function searchNode(string $name, Node $node): ?Node
    {
        if ($node->name() === $name) {
            return $node;
        }

        if (!$node->children()) {
            return null;
        }

        foreach ($node->children() as $childNode) {
            // Return immediately once found; do not continue searching
            if ($theNode = $this->searchNode($name, $childNode)) {
                return $theNode;
            }
        }

        return null;
    }

    /**
     * Depth detection: take the maximum value across all branches
     */
    protected function detectDeep(Node $node, int $deep = 1): int
    {
        if ($node->isLeaf()) {
            return $deep;
        }

        $maxDeep = $deep;
        foreach ($node->children() as $childNode) {
            $maxDeep = max($maxDeep, $this->detectDeep($childNode, $deep + 1));
        }

        return $maxDeep;
    }

    /**
     * Breadth detection: increment breadth by 1 for each node without children
     */
    protected function detectBreadth(Node $node, int &$breadth = 0): int
    {
        if ($node->isLeaf()) {
            return ++$breadth;
        }

        foreach ($node->children() as $childNode) {
            $this->detectBreadth($childNode, $breadth);
        }

        return $breadth;
    }
}
