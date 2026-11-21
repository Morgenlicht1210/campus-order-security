CREATE DATABASE IF NOT EXISTS campus_order_security
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campus_order_security;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS security_events;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS request_limits;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS addresses;
DROP TABLE IF EXISTS dishes;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  demo_password VARCHAR(100) NULL,
  display_name VARCHAR(80) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  phone VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(120) NULL,
  student_no VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dishes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  status ENUM('on','off') NOT NULL DEFAULT 'on',
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dishes_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  campus VARCHAR(50) NOT NULL,
  building VARCHAR(80) NOT NULL,
  room VARCHAR(50) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  address_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  order_no VARCHAR(40) NOT NULL UNIQUE,
  status ENUM('pending','paid','preparing','delivering','completed','cancelled') NOT NULL DEFAULT 'pending',
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  remark VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_orders_address FOREIGN KEY (address_id) REFERENCES addresses(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  dish_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_items_dish FOREIGN KEY (dish_id) REFERENCES dishes(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  method ENUM('wechat','alipay','cash','campus_card') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_at TIMESTAMP NULL,
  status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  dish_id INT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  safe_content TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_comments_dish FOREIGN KEY (dish_id) REFERENCES dishes(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE request_limits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_key VARCHAR(100) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_client_window (client_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE security_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type ENUM('sql_injection','xss','dos','auth','csrf') NOT NULL,
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  payload TEXT NULL,
  defense VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password_hash, demo_password, display_name, role) VALUES
('admin', 'SET_BY_SETUP', 'admin123', '系统管理员', 'admin');

INSERT INTO customers (name, phone, email, student_no) VALUES
('张三', '13800001001', 'zhangsan@example.com', '202483290201'),
('李四', '13800001002', 'lisi@example.com', '202483290202'),
('王五', '13800001003', 'wangwu@example.com', '202483290203'),
('赵六', '13800001004', 'zhaoliu@example.com', '202483290204'),
('孙七', '13800001005', 'sunqi@example.com', '202483290205'),
('周八', '13800001006', 'zhouba@example.com', '202483290206'),
('吴九', '13800001007', 'wujiu@example.com', '202483290207'),
('郑十', '13800001008', 'zhengshi@example.com', '202483290208'),
('钱十一', '13800001009', 'qianshiyi@example.com', '202483290209'),
('冯十二', '13800001010', 'fengshier@example.com', '202483290210'),
('陈十三', '13800001011', 'chenshisan@example.com', '202483290211'),
('褚十四', '13800001012', 'chushisi@example.com', '202483290212'),
('卫十五', '13800001013', 'weishiwu@example.com', '202483290213'),
('蒋十六', '13800001014', 'jiangshiliu@example.com', '202483290214'),
('韩十七', '13800001015', 'hanshiqi@example.com', '202483290215');

INSERT INTO categories (name, description) VALUES
('套餐', '适合午晚餐的固定搭配'),
('主食', '米饭、面食和盖浇饭'),
('饮品', '低糖饮品和校园特色饮品'),
('轻食', '沙拉、低脂餐和加餐');

INSERT INTO dishes (category_id, name, price, stock, status, description) VALUES
(1, '黑椒鸡腿套餐', 18.80, 48, 'on', '含米饭、时蔬和汤'),
(1, '番茄牛腩套餐', 22.50, 35, 'on', '酸甜口味，适合晚餐'),
(2, '鸡蛋炒饭', 12.00, 66, 'on', '经典主食'),
(3, '柠檬红茶', 6.00, 120, 'on', '少糖可选'),
(4, '鸡胸肉沙拉', 16.00, 28, 'on', '轻食推荐'),
(2, '香菇滑鸡盖饭', 17.50, 42, 'on', '热销午餐'),
(3, '青提气泡水', 7.00, 80, 'on', '冰爽饮品'),
(4, '金枪鱼三明治', 14.50, 36, 'on', '下午轻食');

INSERT INTO addresses (customer_id, campus, building, room, is_default) VALUES
(1, '滨江校区', '学生公寓 1 栋', '203', 1),
(2, '滨江校区', '学生公寓 2 栋', '415', 1),
(3, '滨江校区', '学生公寓 3 栋', '512', 1),
(4, '校本部', '学生公寓 6 栋', '308', 1),
(5, '校本部', '学生公寓 8 栋', '416', 1),
(6, '滨江校区', '教学楼 A', '203', 1),
(7, '滨江校区', '学生公寓 5 栋', '618', 1),
(8, '校本部', '学生公寓 2 栋', '109', 1),
(9, '滨江校区', '研究生公寓', '220', 1),
(10, '校本部', '学生公寓 10 栋', '517', 1),
(11, '滨江校区', '学生公寓 7 栋', '404', 1),
(12, '滨江校区', '学生公寓 4 栋', '326', 1),
(13, '校本部', '学生公寓 11 栋', '602', 1),
(14, '滨江校区', '学生公寓 9 栋', '128', 1),
(15, '校本部', '学生公寓 12 栋', '305', 1);

INSERT INTO orders (customer_id, address_id, user_id, order_no, status, total_amount, remark) VALUES
(1, 1, 1, 'CO20260519001', 'paid', 24.80, '少辣'),
(2, 2, 1, 'CO20260519002', 'preparing', 22.50, '下课后送达'),
(3, 3, 1, 'CO20260519003', 'completed', 18.80, '无'),
(4, 4, 1, 'CO20260519004', 'pending', 31.50, '多加米饭'),
(5, 5, 1, 'CO20260519005', 'paid', 17.50, '不要香菜'),
(6, 6, 1, 'CO20260519006', 'delivering', 28.50, '宿舍楼下取餐'),
(7, 7, 1, 'CO20260519007', 'completed', 19.00, '少冰'),
(8, 8, 1, 'CO20260519008', 'cancelled', 14.50, '课程冲突取消'),
(9, 9, 1, 'CO20260519009', 'preparing', 34.50, '加一份餐具'),
(10, 10, 1, 'CO20260519010', 'paid', 23.00, '尽快配送'),
(11, 11, 1, 'CO20260519011', 'completed', 29.50, '正常口味'),
(12, 12, 1, 'CO20260519012', 'pending', 16.00, '放前台'),
(13, 13, 1, 'CO20260519013', 'delivering', 24.50, '到楼下联系'),
(14, 14, 1, 'CO20260519014', 'paid', 18.00, '饮料少糖'),
(15, 15, 1, 'CO20260519015', 'completed', 36.50, '两人一起吃');

INSERT INTO order_items (order_id, dish_id, quantity, unit_price) VALUES
(1, 1, 1, 18.80),
(1, 4, 1, 6.00),
(2, 2, 1, 22.50),
(3, 1, 1, 18.80),
(4, 6, 1, 17.50),
(4, 7, 2, 7.00),
(5, 6, 1, 17.50),
(6, 2, 1, 22.50),
(6, 4, 1, 6.00),
(7, 3, 1, 12.00),
(7, 7, 1, 7.00),
(8, 8, 1, 14.50),
(9, 1, 1, 18.80),
(9, 8, 1, 14.50),
(10, 5, 1, 16.00),
(10, 4, 1, 6.00),
(11, 2, 1, 22.50),
(11, 7, 1, 7.00),
(12, 5, 1, 16.00),
(13, 8, 1, 14.50),
(13, 4, 1, 6.00),
(14, 3, 1, 12.00),
(14, 4, 1, 6.00),
(15, 1, 1, 18.80),
(15, 2, 1, 22.50);

INSERT INTO payments (order_id, method, amount, paid_at, status) VALUES
(1, 'campus_card', 24.80, NOW(), 'paid'),
(2, 'wechat', 22.50, NOW(), 'paid'),
(3, 'alipay', 18.80, NOW(), 'paid'),
(4, 'campus_card', 31.50, NULL, 'unpaid'),
(5, 'wechat', 17.50, NOW(), 'paid'),
(6, 'alipay', 28.50, NOW(), 'paid'),
(7, 'campus_card', 19.00, NOW(), 'paid'),
(8, 'wechat', 14.50, NULL, 'refunded'),
(9, 'campus_card', 34.50, NOW(), 'paid'),
(10, 'alipay', 23.00, NOW(), 'paid'),
(11, 'wechat', 29.50, NOW(), 'paid'),
(12, 'campus_card', 16.00, NULL, 'unpaid'),
(13, 'wechat', 24.50, NOW(), 'paid'),
(14, 'alipay', 18.00, NOW(), 'paid'),
(15, 'campus_card', 36.50, NOW(), 'paid');

INSERT INTO comments (customer_id, dish_id, content, safe_content) VALUES
(1, 1, '鸡腿套餐分量足，晚上还能饱。', '鸡腿套餐分量足，晚上还能饱。'),
(2, 2, '番茄牛腩口味不错，下次还点。', '番茄牛腩口味不错，下次还点。'),
(3, 6, '盖饭送到时还是热的。', '盖饭送到时还是热的。'),
(4, 7, '气泡水很清爽，适合夏天。', '气泡水很清爽，适合夏天。'),
(6, 5, '沙拉鸡胸肉很嫩，酱汁也刚好。', '沙拉鸡胸肉很嫩，酱汁也刚好。'),
(8, 8, '三明治适合下午课间吃。', '三明治适合下午课间吃。'),
(10, 4, '红茶少糖正合适。', '红茶少糖正合适。'),
(12, 1, '这次配送有点慢，不过味道可以。', '这次配送有点慢，不过味道可以。'),
(14, 3, '炒饭火候不错，份量再多一点更好。', '炒饭火候不错，份量再多一点更好。'),
(15, 2, '牛腩和鸡腿都挺好吃，适合两个人拼单。', '牛腩和鸡腿都挺好吃，适合两个人拼单。');
