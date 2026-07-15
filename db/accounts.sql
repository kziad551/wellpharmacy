-- ============================================================
-- Customer accounts, saved wishlist + saved cart.
-- Idempotent: safe to run on local and live.
-- ============================================================

CREATE TABLE IF NOT EXISTS customers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(80)  NOT NULL DEFAULT '',
  last_name     VARCHAR(80)  NOT NULL DEFAULT '',
  email         VARCHAR(160) NOT NULL,
  phone         VARCHAR(40)  NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  address       VARCHAR(400) NOT NULL DEFAULT '',
  governorate   VARCHAR(80)  NOT NULL DEFAULT '',
  city          VARCHAR(120) NOT NULL DEFAULT '',
  verified      TINYINT(1)   NOT NULL DEFAULT 0,
  otp_code      VARCHAR(12)  DEFAULT NULL,
  otp_expires   DATETIME     DEFAULT NULL,
  otp_sent_at   DATETIME     DEFAULT NULL,
  otp_tries     INT          NOT NULL DEFAULT 0,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- saved favourites for logged-in shoppers (guests keep theirs in localStorage)
CREATE TABLE IF NOT EXISTS customer_wishlist (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id  VARCHAR(64) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wish (customer_id, product_id),
  KEY k_wish_cust (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- saved bag for logged-in shoppers
CREATE TABLE IF NOT EXISTS customer_cart (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id  VARCHAR(64) NOT NULL,
  qty         INT NOT NULL DEFAULT 1,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart (customer_id, product_id),
  KEY k_cart_cust (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
