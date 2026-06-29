<?php

namespace App\Domain\Target\Template\Excel;

/**
 * 表格节点
 */
class Node
{
    public const NODE_TOP = '_top_';
    
    protected $name;
    protected $title;
    /**
     * 节点在树中的绝对位置：[行数(深度), 列数(广度)]，从 0 开始编号
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
     * 是否叶子节点
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
     * 获取节点在树中的位置
     */
    public function getPosition(): array
    {
        return $this->pos;
    }

    /**
     * 树深度
     */
    public function deep(): int
    {
        return $this->detectDeep($this);
    }

    /**
     * 树广度
     */
    public function breadth(): int
    {
        return $this->detectBreadth($this);
    }

    /**
     * 根据节点名称查找节点
     */
    public function search(string $name): ?Node
    {
        return $this->searchNode($name, $this);
    }

    /**
     * 获取某结点的所有叶子节点，返回数组
     * @param Node $node
     * @return Node[] 叶子节点数组
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
            // 只要找到则立即返回，不再继续查找
            if ($theNode = $this->searchNode($name, $childNode)) {
                return $theNode;
            }
        }

        return null;
    }

    /**
     * 深度探测：取各条线路探测结果的最大值
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
     * 广度探测：遇到一个没有 children 的节点则广度加 1
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
