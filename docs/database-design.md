# 数据库设计说明

## 1. 设计目标

系统需要描述「学生下单买菜」这条完整链路，涉及身份、地址、菜品、订单、支付、留言等多类对象。单表无法表达这些对象之间的联系，因此采用多表建模，共 11 张表。

## 2. 表结构总览

| # | 表名 | 用途 | 关键字段 |
| --- | --- | --- | --- |
| 1 | `users` | 后台管理员/工作人员 | `username` 唯一、`password_hash`、`role` |
| 2 | `customers` | 学生客户 | `phone` 唯一、`student_no` |
| 3 | `categories` | 菜品分类 | `name` 唯一 |
| 4 | `dishes` | 菜品 | `category_id`、`price`、`stock`、`status` |
| 5 | `addresses` | 配送地址 | `customer_id`、`is_default` |
| 6 | `orders` | 订单主表 | `order_no` 唯一、`status`、`total_amount` |
| 7 | `order_items` | 订单明细 | `order_id`、`dish_id`、`quantity`、`unit_price` |
| 8 | `payments` | 支付记录 | `order_id` 唯一、`method`、`status` |
| 9 | `comments` | 留言 | `customer_id`、`dish_id`、`content`、`safe_content` |
| 10 | `request_limits` | 请求频率窗口 | 唯一键 `(client_key, window_start)` |
| 11 | `security_events` | 安全事件日志 | `event_type`、`severity`、`defense` |

前 9 张表承担业务，后 2 张表支撑安全实验。

## 3. 关键关系

**一对多**

- 一个分类 → 多个菜品（`dishes.category_id`）
- 一个学生 → 多个地址（`addresses.customer_id`）
- 一个学生 → 多个订单（`orders.customer_id`）
- 一个订单 → 多条明细（`order_items.order_id`）

**多对多**

订单与菜品本质是多对多：一张订单含多个菜品，一个菜品可出现在多张订单中。通过中间表 `order_items` 拆成两个一对多，这是标准做法。

```sql
CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id)
  ON UPDATE CASCADE ON DELETE CASCADE,
CONSTRAINT fk_items_dish  FOREIGN KEY (dish_id)  REFERENCES dishes(id)
  ON UPDATE CASCADE ON DELETE RESTRICT
```

**一对一（简化）**

`payments.order_id` 设为唯一，表示一张订单对应一条主支付记录。若未来支持多次支付或退款，应扩展为支付流水表。

## 4. 完整性设计

**实体完整性**：所有表使用自增主键 `id`。

**参照完整性**：大量使用外键约束。删除策略做了区分：

| 场景 | 策略 | 原因 |
| --- | --- | --- |
| 删除订单 → 明细/支付 | `ON DELETE CASCADE` | 明细脱离订单无意义 |
| 删除菜品 → 明细 | `ON DELETE RESTRICT` | 防止已售菜品被删导致历史数据异常 |
| 删除客户 → 地址 | `ON DELETE CASCADE` | 地址属于客户 |
| 删除用户 → 订单 | `ON DELETE SET NULL` | 保留订单，仅解除处理人关联 |

**用户定义完整性**：

- `username`、`phone`、`order_no`、`categories.name` 唯一
- 状态字段使用 `ENUM`，避免非法值写入
- 布尔语义字段用 `TINYINT(1)`（`is_active`、`is_default`）

## 5. 范式分析

| 范式 | 结论 | 依据 |
| --- | --- | --- |
| 1NF | 满足 | 字段均为原子值，无重复组 |
| 2NF | 满足 | 单字段主键；`order_items` 的非主属性完全依赖主键 |
| 3NF | 基本满足 | 分类名、地址、支付均独立成表，无传递依赖 |

具体体现：分类名称不冗余写在 `dishes`，地址不塞进 `customers`，支付不塞进 `orders`。

## 6. 事务设计

下单是最关键的多表写入，位于 `menu.php`：

```php
$pdo->beginTransaction();
// 1. 写入 orders
// 2. 写入 order_items（多条）
// 3. 扣减 dishes.stock
// 4. 写入 payments
// 5. 可选写入 comments
$pdo->commit();
```

若拆开执行，可能出现：

- 订单创建成功但明细缺失
- 订单成功但库存未扣减
- 订单成功但支付记录失败

因此用事务保证「全部成功才提交，任一失败回滚」。

## 7. 可改进点

| 问题 | 建议 |
| --- | --- |
| `customers.student_no` 未加唯一约束 | 增加 `UNIQUE`，避免重复学号 |
| `orders.remark` 与 `comments` 语义重叠 | 明确区分：订单备注 vs 菜品评价 |
| `payments` 一对一 | 支持退款/多次支付时改为流水表 |
| `addresses` 多默认地址 | 增加约束或应用层校验「每人仅一个默认地址」 |
| 查询性能 | 对 `orders.created_at`、`customers.student_no`、`dishes.status` 建索引 |
| 限流表膨胀 | `request_limits` 需定期清理过期窗口数据 |
