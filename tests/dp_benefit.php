<?php

/**
 * 使用动态规划计算叠加券
 * 该思想也可以用来计算整个组合优惠的最优解（叠加券属于组合优惠的一种情况）
 */

 /**
  * 优惠策略接口
  */
interface IBenefitStrategy
{
    /**
     * @param float $calcMoney 用来检验优惠阈值（优惠条件）的金额
     * @param float $origMoney 用来计算优惠额度的金额
     * @return array [新的优惠阈值，减去本次优惠额度后的金额]
     * 输出：优惠后金额，输出金额不能小于 0
     */
    public function calc(float $calcMoney, float $origMoney):  array;

    /**
     * 优惠策略名称
     */
    public function name();
}

/**
 * 决策状态节点
 */
class BfNode
{
    /**
     * @var float 优惠后金额
     */
    public $bMoney;
    /**
     * 本次状态的上一个状态来源，用来回溯选中的优惠策略
     */
    public $prevNodeKey;

    public function __construct($bMoney, $prevNodeKey)
    {
        $this->bMoney = $bMoney;
        $this->prevNodeKey = $prevNodeKey;
    }
}

/**
 * 优惠引擎，计算根据一系列组合优惠得到的最大优惠金额
 * 目前仅支持根据原始金额计算，可以改造用以支持根据升数计算
 */
class BenefitEngine
{
    private $origMoney;
    private $strategies;

    /**
     * @param float $origMoney 原价
     * @param array $strategies 优惠策略（IBenefitStrategy）数组
     */
    public function __construct(float $origMoney, array $strategies)
    {
        $this->origMoney = $origMoney;
        $this->strategies = $strategies;
    }

    /**
     * 计算最大优惠
     * 动态规划思路：
     *      优惠组合属于多步骤决策求最优解模型，我们将每一种优惠策略看作一个决策步骤，那么一共有 count($strategies) 步决策；
     *      每一步决策都有两种选择：使用或者不使用该优惠策略，第 n 步的决策结果取决于第 n - 1 步的结果以及第 n 步的选择；
     *      动态规划做的事情就是：将每一步的所有可能结果（状态）都列出来（合并重复状态），所有决策完成后，取最优解
     * @return [最大优惠金额，相应的优惠组合]
     */
    public function maxBenefit(): array
    {
        /**
         * 动态规划二维数组，第一维 key 表示决策步骤，第二维 key 表示每一步中的所有可能的条件金额（该金额是下一步决策的依据），第二维 value 是 BfNode 对象
         * 如：原价 500 元，有两张满 200 减 10 的券，我们将两张券看做两步骤决策，每步都在前一步的状态基础上做决策：
         * [
         *      0 => [500 => $node1, 300 => $node2],// 不使用券：500 => $node1，使用本步骤的券：300 => $node2（300 = 500 - 200）
         *      1 => [500 => $node1, 300 => $node2, 100 => $node3],
         * ]
         */
        $dpArr = [];

        /**
         * 第一步做特殊处理
         * 初始化时 calcMoney == origMoney
         */
        // 不使用本优惠策略
        $dpArr[0] = [$this->origMoney => new BfNode($this->origMoney, -1)];
        // 使用本优惠策略
        $benefit = $this->strategies[0]->calc($this->origMoney, $this->origMoney);// [计算后的条件值，计算后的金额]
        $dpArr[0][$benefit[0]] = new BfNode($benefit[1], -1);

        for ($i = 1; $i < count($this->strategies); $i++) {
            $dpArr[$i] = [];

            // 选择 1：不使用本优惠策略，则经过本次决策后，原价保持不变（直接把上一步的结果 copy 过来）
            foreach ($dpArr[$i - 1] as $threshold => $bfNode) {
                $dpArr[$i][$threshold] = $bfNode;
            }

            // 选择 2：使用本优惠策略：对上一步的所有结果应用本优惠策略
            foreach ($dpArr[$i - 1] as $threshold => $bfNode) {
                $benefit = $this->strategies[$i]->calc($threshold, $bfNode->bMoney);
                if ($benefit[1] < $bfNode->bMoney) {
                    // 计算出的值小于原始值，说明成功应用该优惠策略
                    if (isset($dpArr[$i][$benefit[0]]) && $dpArr[$i][$benefit[0]]->bMoney <= $benefit[1]) {
                        // 该状态下已经存在更优方案，跳过
                        continue;
                    }

                    // 更新节点
                    $dpArr[$i][$benefit[0]] =  new BfNode($benefit[1], $threshold);
                }
            }
        }

        /**
         * 决策完毕，从最后一步选出最小值（优惠后最小值，也就是最大优惠），并还原相应的优惠组合
         * 回溯方式：回溯时每一步有两种情况：不使用该优惠或者使用该优惠，
         *         我们优先看不使用该优惠，拿本步骤的状态去上一步找，如果找到了，说明在最优方案中确实可以不使用该优惠，如果没有找到，  
         *         说明必须使用该优惠。
         */
        $useStrategies = [];
        $currThreshold = $this->getMinBenefit($dpArr[count($this->strategies) - 1]);
        // 最优值
        $minBfNode = $dpArr[count($this->strategies) - 1][$currThreshold];
        $theMinMoney = $minBfNode->bMoney;

        for ($i = count($this->strategies) - 1; $i >= 0; $i--) {
            if ($i == 0) {
                // 如果是第一步，我们跟原价比较，如果小于原价，说明使用了本步优惠策略
                if ($minBfNode->bMoney < $this->origMoney) {
                    $useStrategies[] = $this->strategies[0];
                }
            } else {
                // 先试图不使用本步骤的优惠
                if (isset($dpArr[$i - 1][$currThreshold]) && $dpArr[$i - 1][$currThreshold] === $minBfNode) {
                    // 前一步有该金额，说明确实可以不使用本步的优惠
                    continue;
                } else {
                    // 前一步没有该金额，说明必须使用本步的优惠
                    $useStrategies[] = $this->strategies[$i];

                    // 迭代计算前一步
                    $currThreshold = $minBfNode->prevNodeKey;
                    $minBfNode = $dpArr[$i - 1][$currThreshold];
                }
            }
        }

        return [$theMinMoney, array_reverse($useStrategies)];
    }

    /**
     * 
     */
    private function getMinBenefit($dpArr)
    {
        $minKey = -1;
        $minNode = null;
        foreach ($dpArr as $threshold => $bfNode) {
            if (!$minNode || $minNode->bMoney > $bfNode->bMoney) {
                $minKey = $threshold;
                $minNode = $bfNode;
            }
        }

        return $minKey;
    }
}

/**
 * 测试
 * 此处我们测试券叠加的场景
 */

/**
 * 优惠券类
 * 简单起见，此处仅列出必须的属性，以及直接将属性设置成 public
 */
class Coupon
{
    // 普通券
    const TYPE_COMMON = 1;
    // 折扣券
    const TYPE_DISCOUNT = 2;

    public $name;
    /**
     * @var float 阈值，满多少可用，单位元
     */
    public $threshold;

    /**
     * @var float 该券可抵扣的金额，单位元
     */
    public $money;

    /**
     * @var float 该券可使用的折扣（折扣券），0 - 1 的数，1 表示不打折
     */
    public $discount;

    /**
     * 券类型：普通券、折扣券
     * @var int
     */
    public $couponType;

    /**
     * @var bool 是否可叠加使用
     */
    public $isOverlay;

    public function __construct($name, $threshold, $money, $discount = 1, $type = self::TYPE_COMMON, $isOverlay = true)
    {
        $this->name = $name;
        $this->threshold = floatval($threshold >= 0 ? $threshold : 0);
        $this->money = round(floatval($money >= 0 ? $money : 0), 2);
        $this->discount = floatval($discount);
        if ($this->discount < 0) {
            $this->discount = 0;
        } elseif ($this->discount > 1) {
            $this->discount = 1;
        }
        $this->couponType = intval($type);
        $this->isOverlay = boolval($isOverlay);
    }
}

/**
 * 券叠加策略
*/
class CouponStrategy implements IBenefitStrategy
{
    private $coupon;

    public function __construct(Coupon $coupon)
    {
        $this->coupon = $coupon;
    }

    public function name()
    {
        return "叠加券：" . $this->coupon->name;
    }

    /**
     * @param float $calcMoney 用来检验优惠阈值（优惠条件）的金额
     * @param float $origMoney 用来计算优惠额度的金额
     * @return array [新的优惠阈值，减去本次优惠额度后的金额]
     */
    public function calc(float $calcMoney, float $origMoney): array
    {
        if ($origMoney < 0) {
            return [$calcMoney, $origMoney];
        }

        $result = $this->coupon->couponType === Coupon::TYPE_DISCOUNT 
                ? $this->calcDiscountCoupon($calcMoney, $origMoney)
                : $this->calcCommonCoupon($calcMoney, $origMoney);
        return $result;
    }

    /**
     * 计算普通券优惠
     */
    private function calcCommonCoupon(float $calcMoney, float $origMoney): array
    {
        if ($calcMoney < $this->coupon->threshold || $origMoney < $this->coupon->money) {
            return [$calcMoney, $origMoney];
        }

        return [$calcMoney - $this->coupon->threshold, $origMoney - $this->coupon->money];
    }

    /**
     * 计算折扣券优惠
     */
    private function calcDiscountCoupon(float $calcMoney, float $origMoney): array
    {
        if ($calcMoney < $this->coupon->threshold) {
            return [$calcMoney, $origMoney];
        }

        return [$calcMoney - $this->coupon->threshold, round($origMoney * $this->coupon->discount, 2)];
    }
}

$origMoney = 500;

// 场景一：三张满 200 减 20 的券
$sts1 = [
    new CouponStrategy(new Coupon('a', 200, 20)),
    new CouponStrategy(new Coupon('b', 200, 20)),
    new CouponStrategy(new Coupon('c', 200, 20)),
];

// 场景二：选取 a b c
$sts2 = [
    new CouponStrategy(new Coupon('a', 200, 20)),
    new CouponStrategy(new Coupon('b', 200, 0, 0.6, Coupon::TYPE_DISCOUNT)),
    new CouponStrategy(new Coupon('c', 100, 20)),
];

// 场景三：选取：d e f g h
$sts3 = [
    new CouponStrategy(new Coupon('a', 200, 20)),
    new CouponStrategy(new Coupon('b', 200, 30)),
    new CouponStrategy(new Coupon('c', 200, 25)),
    new CouponStrategy(new Coupon('d', 150, 50)),
    new CouponStrategy(new Coupon('e', 100, 30)),
    new CouponStrategy(new Coupon('f', 40, 40)),
    new CouponStrategy(new Coupon('g', 50, 40)),
    new CouponStrategy(new Coupon('h', 10, 20)),
    new CouponStrategy(new Coupon('i', 420, 160)),
];

// 计算
$engine = new BenefitEngine($origMoney, $sts3);
$result = $engine->maxBenefit();

echo "money:" . $result[0] . "\n";
echo "use benefit:";
foreach ($result[1] as $st) {
    echo $st->name()."，";
}