<?php

namespace App\Domain\Target\Template\Excel;

/**
 * 行标头
 */
class RowHead extends Node
{
    use NodeParser;

    // 节点关联的行数，只有叶子节点有效
    private $rowCount;

    public function __construct(string $name = '', string $title = '', Style $style = null, int $rowCount = 1)
    {
        $this->name = $name;
        $this->title = $title;
        $this->style = $style;
        $this->rowCount = $rowCount;
    }

    /**
     * 叶节点关联的行数
     */
    public function rowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * 重写广度探测逻辑：遇到一个没有 children 的节点则广度加 row_count（该节点关联的行数）
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
