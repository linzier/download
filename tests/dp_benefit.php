<?php

/**
 * Calculate stackable coupons using dynamic programming
 * This approach can also be used to find the optimal solution for all combinations of discounts
 * (stackable coupons are a special case of discount combinations)
 */

 /**
  * Benefit strategy interface
  */
interface IBenefitStrategy
{
    /**
     * @param float $calcMoney Amount used to check the discount threshold (discount condition)
     * @param float $origMoney Amount used to calculate the discount value
     * @return array [new discount threshold, amount after subtracting the current discount value]
     * Output: amount after discount, which must not be less than 0
     */
    public function calc(float $calcMoney, float $origMoney):  array;

    /**
     * Benefit strategy name
     */
    public function name();
}

/**
 * Decision state node
 */
class BfNode
{
    /**
     * @var float Amount after discount
     */
    public $bMoney;
    /**
     * The previous state node of the current state, used to backtrack the selected benefit strategy
     */
    public $prevNodeKey;

    public function __construct($bMoney, $prevNodeKey)
    {
        $this->bMoney = $bMoney;
        $this->prevNodeKey = $prevNodeKey;
    }
}

/**
 * Benefit engine, calculates the maximum discount amount from a series of discount combinations
 * Currently only supports calculation based on original amount, can be modified to support calculation by volume
 */
class BenefitEngine
{
    private $origMoney;
    private $strategies;

    /**
     * @param float $origMoney Original price
     * @param array $strategies Array of benefit strategies (IBenefitStrategy)
     */
    public function __construct(float $origMoney, array $strategies)
    {
        $this->origMoney = $origMoney;
        $this->strategies = $strategies;
    }

    /**
     * Calculate maximum discount
     * Dynamic programming approach:
     *      The discount combination is a multi-step decision optimization model. Each discount strategy is treated as a decision step,
     *      resulting in count($strategies) decision steps in total;
     *      Each step has two choices: use or not use the discount strategy. The decision result at step n depends on the result of
     *      step n - 1 and the choice at step n;
     *      Dynamic programming enumerates all possible results (states) at each step (merging duplicate states),
     *      and selects the optimal solution after all decisions are completed
     * @return [maximum discount amount, corresponding discount combination]
     */
    public function maxBenefit(): array
    {
        /**
         * DP 2D array: the first dimension key represents the decision step, the second dimension key represents all possible
         * condition amounts at each step (this amount is the basis for the next step's decision), the second dimension value
         * is a BfNode object.
         * Example: original price is 500, two coupons of "spend 200 save 10". We treat the two coupons as a two-step decision,
         * each step making decisions based on the previous step's states:
         * [
         *      0 => [500 => $node1, 300 => $node2],// no coupon: 500 => $node1, use this step's coupon: 300 => $node2 (300 = 500 - 200)
         *      1 => [500 => $node1, 300 => $node2, 100 => $node3],
         * ]
         */
        $dpArr = [];

        /**
         * Special handling for the first step
         * Initially calcMoney == origMoney
         */
        // Do not use this benefit strategy
        $dpArr[0] = [$this->origMoney => new BfNode($this->origMoney, -1)];
        // Use this benefit strategy
        $benefit = $this->strategies[0]->calc($this->origMoney, $this->origMoney);// [condition value after calculation, amount after calculation]
        $dpArr[0][$benefit[0]] = new BfNode($benefit[1], -1);

        for ($i = 1; $i < count($this->strategies); $i++) {
            $dpArr[$i] = [];

            // Choice 1: Do not use this benefit strategy, the original price remains unchanged (directly copy the results from the previous step)
            foreach ($dpArr[$i - 1] as $threshold => $bfNode) {
                $dpArr[$i][$threshold] = $bfNode;
            }

            // Choice 2: Use this benefit strategy: apply this benefit strategy to all results from the previous step
            foreach ($dpArr[$i - 1] as $threshold => $bfNode) {
                $benefit = $this->strategies[$i]->calc($threshold, $bfNode->bMoney);
                if ($benefit[1] < $bfNode->bMoney) {
                    // The calculated value is less than the original value, indicating this benefit strategy was successfully applied
                    if (isset($dpArr[$i][$benefit[0]]) && $dpArr[$i][$benefit[0]]->bMoney <= $benefit[1]) {
                        // A better solution already exists for this state, skip
                        continue;
                    }

                    // Update node
                    $dpArr[$i][$benefit[0]] =  new BfNode($benefit[1], $threshold);
                }
            }
        }

        /**
         * All decisions are complete. Select the minimum value from the last step (minimum after discount = maximum discount),
         * and reconstruct the corresponding discount combination.
         * Backtracking approach: at each step during backtracking, there are two cases: not using the discount or using it.
         *         We first check whether the discount was not used by looking up the current step's state in the previous step.
         *         If found, it means the optimal solution indeed does not use this discount. If not found,
         *         it means this discount must be used.
         */
        $useStrategies = [];
        $currThreshold = $this->getMinBenefit($dpArr[count($this->strategies) - 1]);
        // Optimal value
        $minBfNode = $dpArr[count($this->strategies) - 1][$currThreshold];
        $theMinMoney = $minBfNode->bMoney;

        for ($i = count($this->strategies) - 1; $i >= 0; $i--) {
            if ($i == 0) {
                // For the first step, compare with the original price. If less than the original price, this step's benefit strategy was used
                if ($minBfNode->bMoney < $this->origMoney) {
                    $useStrategies[] = $this->strategies[0];
                }
            } else {
                // First try not using this step's discount
                if (isset($dpArr[$i - 1][$currThreshold]) && $dpArr[$i - 1][$currThreshold] === $minBfNode) {
                    // The previous step has this amount, confirming this step's discount was not used
                    continue;
                } else {
                    // The previous step does not have this amount, confirming this step's discount must be used
                    $useStrategies[] = $this->strategies[$i];

                    // Iteratively calculate the previous step
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
 * Test
 * Here we test the scenario of stackable coupons
 */

/**
 * Coupon class
 * For simplicity, only required properties are listed, and properties are directly set as public
 */
class Coupon
{
    // Regular coupon
    const TYPE_COMMON = 1;
    // Discount coupon
    const TYPE_DISCOUNT = 2;

    public $name;
    /**
     * @var float Threshold, minimum spend required, in yuan
     */
    public $threshold;

    /**
     * @var float Deductible amount for this coupon, in yuan
     */
    public $money;

    /**
     * @var float Discount rate for this coupon (discount coupon), a value between 0 - 1, where 1 means no discount
     */
    public $discount;

    /**
     * Coupon type: regular coupon, discount coupon
     * @var int
     */
    public $couponType;

    /**
     * @var bool Whether it can be used in combination
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
 * Stackable coupon strategy
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
        return "Stackable coupon: " . $this->coupon->name;
    }

    /**
     * @param float $calcMoney Amount used to check the discount threshold (discount condition)
     * @param float $origMoney Amount used to calculate the discount value
     * @return array [new discount threshold, amount after subtracting the current discount value]
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
     * Calculate regular coupon discount
     */
    private function calcCommonCoupon(float $calcMoney, float $origMoney): array
    {
        if ($calcMoney < $this->coupon->threshold || $origMoney < $this->coupon->money) {
            return [$calcMoney, $origMoney];
        }

        return [$calcMoney - $this->coupon->threshold, $origMoney - $this->coupon->money];
    }

    /**
     * Calculate discount coupon discount
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

// Scenario 1: Three coupons of "spend 200 save 20"
$sts1 = [
    new CouponStrategy(new Coupon('a', 200, 20)),
    new CouponStrategy(new Coupon('b', 200, 20)),
    new CouponStrategy(new Coupon('c', 200, 20)),
];

// Scenario 2: Select a, b, c
$sts2 = [
    new CouponStrategy(new Coupon('a', 200, 20)),
    new CouponStrategy(new Coupon('b', 200, 0, 0.6, Coupon::TYPE_DISCOUNT)),
    new CouponStrategy(new Coupon('c', 100, 20)),
];

// Scenario 3: Select d, e, f, g, h
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

// Calculate
$engine = new BenefitEngine($origMoney, $sts3);
$result = $engine->maxBenefit();

echo "money:" . $result[0] . "\n";
echo "use benefit:";
foreach ($result[1] as $st) {
    echo $st->name().", ";
}
