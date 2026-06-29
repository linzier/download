<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * 根据配置文件解析出 excel 节点树
 */
trait NodeParser
{
    public static function parse(array $config): Node
    {
        // 加入顶层节点
        if (!isset($config['name']) || $config['name'] !== Node::NODE_TOP) {
            $conf = [
                'name' => Node::NODE_TOP,
                'children' => $config,
            ];
        } else {
            $conf = $config;
        }

        $node = self::parseNode($conf);

        // 计算每个节点在 Excel 中的位置
        self::calcPosition($node);

        return $node;
    }

    protected static function calcPosition(Node $node)
    {
        self::calcPos($node, -1, 0, []);
    }

    /**
     * 注意：为了方便理解，此处统一认为位置数组中的第一维是行号，第二维是列号（实际中对于 RowHead 来说需要反转过来理解）
     * 第一个子节点列号和父节点的相同
     * 后续子节点相对于父节点的列偏移量是前面所有子节点的广度之和
     * @param Node $node 要计算的节点
     * @param int $parentRowNum 父节点行号
     * @param int $parentColNum 父节点列号
     * @param array $neighbours 本节点的前置邻居节点列表（邻居是指同一个父节点下的节点）
     */
    protected static function calcPos(Node $node, int $parentRowNum, int $parentColNum, array $neighbours)
    {
        $row = $parentRowNum + 1;
        if (!$neighbours) {
            // 没有前置邻居（第一个节点），则取父节点的列号
            $col = $parentColNum;
        } else {
            // 否则，取父节点列号 + 所有邻居广度之和（相对于父节点的列偏移）
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
            throw new Exception("模板格式错误：name 和 title 至少提供一个", ErrCode::PARAM_VALIDATE_FAIL);
        }
    }

    abstract protected static function createNode(array $conf): Node;
}
