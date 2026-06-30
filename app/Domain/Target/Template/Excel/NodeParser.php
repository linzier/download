<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * Parse configuration into an Excel node tree
 */
trait NodeParser
{
    public static function parse(array $config): Node
    {
        // Add top-level node
        if (!isset($config['name']) || $config['name'] !== Node::NODE_TOP) {
            $conf = [
                'name' => Node::NODE_TOP,
                'children' => $config,
            ];
        } else {
            $conf = $config;
        }

        $node = self::parseNode($conf);

        // Calculate each node's position in Excel
        self::calcPosition($node);

        return $node;
    }

    protected static function calcPosition(Node $node)
    {
        self::calcPos($node, -1, 0, []);
    }

    /**
     * Note: for clarity, the first dimension of the position array is treated as row number and the second as column number
     * (in practice, RowHead needs to interpret this reversed).
     * The first child node shares the same column number as the parent.
     * Subsequent child nodes have a column offset relative to the parent equal to the sum of all preceding siblings' breadth.
     * @param Node $node The node to calculate
     * @param int $parentRowNum Parent node row number
     * @param int $parentColNum Parent node column number
     * @param array $neighbours List of preceding sibling nodes (siblings share the same parent)
     */
    protected static function calcPos(Node $node, int $parentRowNum, int $parentColNum, array $neighbours)
    {
        $row = $parentRowNum + 1;
        if (!$neighbours) {
            // No preceding siblings (first node); use the parent's column number
            $col = $parentColNum;
        } else {
            // Otherwise, use parent's column number + sum of all siblings' breadth (column offset relative to parent)
            $offset = 0;
            foreach ($neighbours as $neighbour) {
                $offset += $neighbour->breadth();
            }

            $col = $parentColNum + $offset;
        }

        $node->setPosition($row, $col);

        $nbs = [];
        foreach ($node->children() as $subNode) {
            self::calcPos($subNode, $row, $col, $nbs);
            $nbs[] = $subNode;
        }
    }

    protected static function parseNode(array $cfg): Node
    {
        self::validate($cfg);

        $node = self::createNode($cfg);

        if (isset($cfg['children']) && $cfg['children']) {
            foreach ($cfg['children'] as $subColCfg) {
                $node->appendChild(self::parseNode($subColCfg));
            }
        }

        return $node;
    }

    protected static function validate(array $colCfg)
    {
        if (!isset($colCfg['name']) && !isset($colCfg['title'])) {
            throw new Exception("Template format error: at least one of 'name' or 'title' must be provided", ErrCode::PARAM_VALIDATE_FAIL);
        }
    }

    abstract protected static function createNode(array $conf): Node;
}
