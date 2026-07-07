<?php
/* ONE-TIME DB IMPORT — loads the local dump into the live DB. DELETE THIS FILE AFTER USE. */
if (($_GET['key'] ?? '') !== 'imp-8x4k2m9q') { http_response_code(404); exit; }
@set_time_limit(180); header('Content-Type: text/plain; charset=utf-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
require __DIR__ . '/inc/config.php';
$m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if ($m->connect_errno) { http_response_code(500); exit('DB connect failed: ' . $m->connect_error); }
$m->set_charset('utf8mb4');
$sql = <<<'WPDUMP'
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: wellpharmacy_dev
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(120) DEFAULT '',
  `role` enum('admin','staff') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_user` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$12$KMqCEZZYYxVuh9TK0gttseODpJLm5e2wTNSjYBpVp1IrYtcpgr/kC','Store Admin','admin','2026-06-29 11:06:36');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `color` varchar(16) DEFAULT '',
  `logo` varchar(500) DEFAULT '',
  `featured` tinyint(4) NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  `logo_mode` enum('auto','logo','name','both') NOT NULL DEFAULT 'auto',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brand_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,'CeraVe','cerave','#0057B8','',1,0,'auto'),(2,'La Roche-Posay','la-roche-posay','#009CB7','',1,1,'auto'),(3,'The Ordinary','the-ordinary','#111111','',1,2,'auto'),(4,'Bioderma','bioderma','#008E83','',1,3,'auto'),(5,'Avène','av-ne','#0093C9','',1,4,'auto'),(6,'Vichy','vichy','#D81E26','',1,5,'auto'),(7,'Eucerin','eucerin','#0046AD','',1,6,'auto'),(8,'Solgar','solgar','#A07C1F','',1,7,'auto'),(9,'Aveeno','aveeno','#FFBC57','',1,8,'auto'),(10,'Bepanthen','bepanthen','','',0,9,'auto'),(11,'Cetaphil','cetaphil','','',0,10,'auto'),(12,'Centrum','centrum','','',0,11,'auto'),(13,'Cetraben','cetraben','','',0,12,'auto'),(14,'Durex','durex','','',0,13,'auto'),(15,'Eau Thermale','eau-thermale','','',0,14,'auto'),(16,'Filorga','filorga','','',0,15,'auto'),(17,'Garnier','garnier','','',0,16,'auto'),(18,'Klorane','klorane','','',0,17,'auto'),(19,'Lierac','lierac','','',0,18,'auto'),(20,'Mustela','mustela','','',0,19,'auto'),(21,'Nuxe','nuxe','','',0,20,'auto'),(22,'Neutrogena','neutrogena','','',0,21,'auto'),(23,'Nivea','nivea','','',0,22,'auto'),(24,'Pigeon','pigeon','','',0,23,'auto'),(25,'QV Skin','qv-skin','','',0,24,'auto'),(26,'Rilastil','rilastil','','',0,25,'auto'),(27,'Sebamed','sebamed','','',0,26,'auto'),(28,'Sebderm','sebderm','','',0,27,'auto'),(29,'Uriage','uriage','','',0,28,'auto'),(30,'Weleda','weleda','','',0,29,'auto'),(31,'Test Brand','test-brand','#FBFF00','',1,0,'auto');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `image` varchar(500) DEFAULT '',
  `in_nav` tinyint(4) NOT NULL DEFAULT 1,
  `is_cross` tinyint(4) NOT NULL DEFAULT 0,
  `is_sale` tinyint(4) NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Skincare','skincare','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=400&q=80',1,0,0,0),(2,'Haircare','haircare','https://images.unsplash.com/photo-1522338242992-e1a54906a8da?auto=format&fit=crop&w=400&q=80',1,0,0,1),(3,'Wellness','wellness','https://images.unsplash.com/photo-1584017911766-d451b3d0e843?auto=format&fit=crop&w=400&q=80',1,0,0,2),(4,'Makeup','makeup','https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=400&q=80',1,0,0,3),(5,'Personal Care','personal-care','https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?auto=format&fit=crop&w=400&q=80',1,0,0,4),(6,'Mom & Baby','mom-baby','https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=400&q=80',1,0,0,5),(7,'Sexual Wellness','sexual-wellness','https://images.unsplash.com/photo-1571875257727-256c39da42af?auto=format&fit=crop&w=400&q=80',1,0,0,6),(8,'Health Conditions','health-conditions','https://images.unsplash.com/photo-1584017911766-d451b3d0e843?auto=format&fit=crop&w=400&q=80',1,1,0,7),(9,'Test Category','test-category','uploads/20260703-193620-94341830.jpg',1,0,0,0);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `type` enum('percent','fixed','freeship') NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_spend` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expires_at` date DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,'WELL10','percent',10.00,0.00,NULL,NULL,0,1),(2,'GLOW200','percent',20.00,40.00,NULL,NULL,0,1),(3,'FREESHIP','freeship',0.00,0.00,NULL,NULL,0,1),(4,'TEST20','percent',20.00,0.00,NULL,NULL,0,1);
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `home_sections`
--

DROP TABLE IF EXISTS `home_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `home_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('new_arrivals','brand','category') NOT NULL DEFAULT 'brand',
  `brand` varchar(120) NOT NULL DEFAULT '',
  `eyebrow` varchar(120) NOT NULL DEFAULT '',
  `title` varchar(160) NOT NULL DEFAULT '',
  `subtitle` varchar(300) NOT NULL DEFAULT '',
  `show_title` tinyint(4) NOT NULL DEFAULT 1,
  `item_count` int(11) NOT NULL DEFAULT 5,
  `cols` int(11) NOT NULL DEFAULT 5,
  `enabled` tinyint(4) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`,`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `home_sections`
--

LOCK TABLES `home_sections` WRITE;
/*!40000 ALTER TABLE `home_sections` DISABLE KEYS */;
INSERT INTO `home_sections` VALUES (1,'new_arrivals','','just dropped','','Fresh picks, hand-selected by our pharmacists.',1,10,4,1,10),(2,'brand','Aurelle','the aurelle edit','','Clinical skincare for a clear, radiant glow.',1,5,5,1,40),(3,'brand','IntimaCare','intimate care','','Gentle, pH-balanced essentials for everyday comfort.',1,5,5,1,20),(4,'brand','PHbalance','healthy hair','','Scalp-first haircare that keeps your strands strong.',1,5,5,1,20),(5,'brand','VitaWell','feel good within','','Daily vitamins and supplements, pharmacist-picked.',1,5,5,1,20),(9,'category','','shop by ritual','','Find the range that fits your routine.',1,4,4,1,5);
/*!40000 ALTER TABLE `home_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_posts`
--

DROP TABLE IF EXISTS `journal_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `category` varchar(60) DEFAULT '',
  `excerpt` varchar(300) DEFAULT '',
  `body` mediumtext DEFAULT NULL,
  `image` varchar(500) DEFAULT '',
  `author` varchar(120) DEFAULT '',
  `read_min` int(11) NOT NULL DEFAULT 5,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `published_at` date DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_posts`
--

LOCK TABLES `journal_posts` WRITE;
/*!40000 ALTER TABLE `journal_posts` DISABLE KEYS */;
INSERT INTO `journal_posts` VALUES (1,'Vitamin C vs. Niacinamide: which brightener wins?','vitamin-c-vs-niacinamide-which-brightener-wins','Skincare','A pharmacist-written guide from THE WELL journal.','<p>Full article coming soon — edit this post in the admin panel.</p>','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=800&q=80','Dr. Lara Haddad, PharmD',6,'published','2026-06-29',0),(2,'The 5-step glass-skin routine your derm approves','the-5-step-glass-skin-routine-your-derm-approves','Routines','A pharmacist-written guide from THE WELL journal.','<p>Full article coming soon — edit this post in the admin panel.</p>','https://images.unsplash.com/photo-1620916297397-a4a5402a3c6c?auto=format&fit=crop&w=800&q=80','Clinical Team',7,'published','2026-06-29',1),(3,'Magnesium for sleep: the pharmacist’s guide','magnesium-for-sleep-the-pharmacist-s-guide','Wellness','A pharmacist-written guide from THE WELL journal.','<p>Full article coming soon — edit this post in the admin panel.</p>','https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=80','Dr. Rami N., PharmD',5,'published','2026-06-29',2),(4,'My First Test Blog','my-first-test-blog','Skincare','A quick test post to prove the blog is admin-controlled.','<h3>Hello from the admin</h3><p><i>If you can read this on the site, the journal is fully connected.</i></p>','uploads/20260703-195104-c7c1d90d.jpg','Store Admin',5,'published','2026-07-03',0);
/*!40000 ALTER TABLE `journal_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `email` varchar(160) DEFAULT '',
  `phone` varchar(40) DEFAULT '',
  `topic` varchar(80) DEFAULT '',
  `order_no` varchar(40) DEFAULT '',
  `body` text NOT NULL,
  `is_read` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` varchar(64) NOT NULL,
  `name` varchar(200) NOT NULL,
  `brand` varchar(120) DEFAULT '',
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) NOT NULL,
  `customer_name` varchar(160) NOT NULL,
  `email` varchar(160) DEFAULT '',
  `phone` varchar(40) NOT NULL,
  `address` varchar(400) NOT NULL,
  `governorate` varchar(80) DEFAULT '',
  `city` varchar(120) DEFAULT '',
  `payment_method` enum('cod','areeba') NOT NULL DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `order_status` enum('new','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(40) DEFAULT '',
  `notes` text DEFAULT NULL,
  `gateway_ref` varchar(120) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_no` (`order_no`),
  KEY `idx_status` (`order_status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) NOT NULL,
  `title` varchar(200) NOT NULL,
  `intro` varchar(400) DEFAULT '',
  `body` mediumtext DEFAULT NULL,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'shipping-delivery','Shipping & Delivery','Fast, reliable delivery across Lebanon — with Cash on Delivery available everywhere.','<h3>Where we deliver</h3><p>We deliver to <b>every governorate in Lebanon</b>. Orders are dispatched from our Beirut pharmacy and handled with care.</p>\n<h3>Delivery times</h3><ul><li><b>Beirut:</b> same-day dispatch for orders placed before 2pm; delivery within 24 hours.</li><li><b>Outside Beirut:</b> 2 business days on average.</li></ul>\n<h3>Shipping fees</h3><ul><li>Beirut: a small flat delivery fee applies at checkout.</li><li>Outside Beirut: flat fee by area, shown at checkout.</li><li><b>Free shipping</b> on orders above the threshold shown in your cart.</li></ul>\n<h3>Cash on Delivery</h3><p>Pay in cash when your order arrives — available across Lebanon. A small COD handling fee may apply.</p>\n<h3>Order tracking</h3><p>You will receive updates by phone/WhatsApp. You can also use the <a href=\"order-tracking\">Track Order</a> page with your order number.</p>','published',0),(2,'returns-refunds','Returns & Refunds','Changed your mind? Our hassle-free returns make it easy.','<h3>Our promise</h3><p>If something isn\'t right, we\'ll make it right. You can return most items within <b>14 days</b> of delivery.</p>\n<h3>What can be returned</h3><ul><li>Unopened items in their original, sealed packaging.</li><li>Items that arrived damaged, faulty, or incorrect (we cover return costs).</li></ul>\n<h3>What can\'t be returned</h3><p>For health &amp; safety reasons, some products are non-returnable once opened — including certain skincare, supplements, intimate and baby care items. This does not affect your statutory rights.</p>\n<h3>How to start a return</h3><ol><li>Contact us via <a href=\"contact\">the contact page</a> or WhatsApp with your order number.</li><li>Our team confirms eligibility and arranges pickup or drop-off.</li><li>Once received and checked, your refund is issued.</li></ol>\n<h3>Refunds</h3><p>Refunds are processed to your original payment method, or as store credit for Cash-on-Delivery orders, within 5–7 business days.</p>','published',1),(3,'faq','Frequently Asked Questions','Quick answers to the questions we hear most.','<h3>Do you deliver across Lebanon?</h3><p>Yes — to every governorate, with same-day dispatch in Beirut and Cash on Delivery available everywhere.</p>\n<h3>Is Cash on Delivery available?</h3><p>Absolutely. Pay in cash when your order arrives. A small COD handling fee may apply.</p>\n<h3>Are your products authentic?</h3><p>100%. Every product is sourced directly from trusted brands and quality-checked by our licensed pharmacists.</p>\n<h3>Can I talk to a pharmacist before buying?</h3><p>Yes — reach us by WhatsApp or the contact form. Our licensed pharmacists answer product and wellness questions, privately.</p>\n<h3>What payment methods do you accept?</h3><p>Cash on Delivery and secure card payment at checkout.</p>\n<h3>How long does delivery take?</h3><p>Same-day in Beirut (order before 2pm); 2 business days on average elsewhere in Lebanon.</p>\n<h3>What is your returns policy?</h3><p>Hassle-free returns within 14 days on most items. Some health products are non-returnable once opened, for safety. See <a href=\"returns-refunds\">Returns &amp; Refunds</a>.</p>','published',2);
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` varchar(64) NOT NULL,
  `name` varchar(200) NOT NULL,
  `brand` varchar(120) NOT NULL DEFAULT '',
  `category` varchar(80) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `was` decimal(10,2) DEFAULT NULL,
  `sale_pct` int(11) DEFAULT NULL,
  `badge` varchar(20) DEFAULT '',
  `rating` decimal(2,1) NOT NULL DEFAULT 5.0,
  `reviews` int(11) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock` int(11) NOT NULL DEFAULT 5,
  `kw` varchar(60) DEFAULT '',
  `descr` varchar(180) DEFAULT '',
  `long_desc` text DEFAULT NULL,
  `image` varchar(500) DEFAULT '',
  `hover_image` varchar(500) DEFAULT '',
  `feat_latest` tinyint(4) NOT NULL DEFAULT 0,
  `feat_wellness` tinyint(4) NOT NULL DEFAULT 0,
  `home_sort` int(11) NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cat` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_feat` (`feat_latest`,`feat_wellness`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES ('aurelle-azelaic','Azelaic Acid 10% Clarifying Gel','Aurelle','Skincare',21.00,NULL,NULL,'derm',0.0,0,3,5,'clarify','Clarifying gel','Pharmacist-vetted and clinically-backed. Clarifying gel from Aurelle. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',1,0,4,7,'active','2026-06-29 11:06:36'),('aurelle-ceramide','Ceramide Repair Cream','Aurelle','Skincare',24.00,NULL,NULL,'',0.0,0,30,5,'repair','Barrier repair moisturizer',NULL,'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',0,0,0,102,'active','2026-07-07 10:19:57'),('aurelle-hydra','Hyaluronic Hydra Serum','Aurelle','Skincare',22.00,NULL,NULL,'',0.0,0,30,5,'hydrate','Plumping hydration serum',NULL,'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',0,0,0,100,'active','2026-07-07 10:19:57'),('aurelle-niacin','Niacinamide 10% + Zinc Pore Serum','Aurelle','Skincare',19.00,24.00,20,'',0.0,0,33,5,'clarify','Pore-refining serum','Pharmacist-vetted and clinically-backed. Pore-refining serum from Aurelle. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80',0,0,0,2,'active','2026-06-29 11:06:36'),('aurelle-vitc','Vitamin C Glow Drops','Aurelle','Skincare',26.00,NULL,NULL,'',0.0,0,30,5,'glow','Brightening vitamin C',NULL,'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?auto=format&fit=crop&w=600&q=80',0,0,0,101,'active','2026-07-07 10:19:57'),('ceravita-cer','Ceramide Repair Moisturizer','CeraVita','Skincare',24.00,NULL,NULL,'ff',0.0,0,38,5,'repair','Barrier moisturizer','Pharmacist-vetted and clinically-backed. Barrier moisturizer from CeraVita. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',0,0,0,5,'active','2026-06-29 11:06:36'),('dermavera-ha','Pure Hyaluronic Acid 2% + B5','Dermavera','Skincare',22.00,NULL,NULL,'best',0.0,0,60,5,'hydrate','Deep hydration','Pharmacist-vetted and clinically-backed. Deep hydration from Dermavera. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',0,0,0,1,'active','2026-06-29 11:06:36'),('ferrovita-iron','Gentle Iron + Vitamin C','FerroVita','Wellness',15.00,NULL,NULL,'ff',0.0,0,29,5,'iron','Gentle iron + C','Pharmacist-vetted and clinically-backed. Gentle iron + C from FerroVita. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',0,0,0,15,'active','2026-06-29 11:06:36'),('glowcollagen','Marine Collagen Peptides','GlowCollagen','Wellness',32.00,NULL,NULL,'new',0.0,0,5,5,'glow','Marine collagen','Pharmacist-vetted and clinically-backed. Marine collagen from GlowCollagen. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80',0,0,0,8,'active','2026-06-29 11:06:36'),('hydraluna-clean','Gentle Hydrating Cleanser','Hydraluna','Skincare',16.00,NULL,NULL,'vegan',0.0,0,71,5,'cleanse','Gentle daily cleanser','Pharmacist-vetted and clinically-backed. Gentle daily cleanser from Hydraluna. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',0,0,0,6,'active','2026-06-29 11:06:36'),('immunowell-zinc','Zinc + Vitamin C Daily Defense','ImmunoWell','Wellness',13.00,14.40,10,'',0.0,0,48,5,'immunity','Daily defense','Pharmacist-vetted and clinically-backed. Daily defense from ImmunoWell. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',0,1,4,17,'active','2026-06-29 11:06:36'),('intima-gel','Water-Based Intimate Gel','IntimaCare','Sexual Wellness',17.00,NULL,NULL,'vegan',0.0,0,36,5,'comfort','Water-based gel','Pharmacist-vetted and clinically-backed. Water-based gel from IntimaCare. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',0,0,0,23,'active','2026-06-29 11:06:36'),('intima-prebiotic','Prebiotic Intimate Serum','IntimaCare','Sexual Wellness',19.00,NULL,NULL,'',0.0,0,30,5,'balance','Microbiome-friendly serum',NULL,'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',0,0,0,105,'active','2026-07-07 10:19:57'),('intima-soothe','Soothing Intimate Cream','IntimaCare','Sexual Wellness',16.00,NULL,NULL,'',0.0,0,30,5,'soothe','Calming daily cream',NULL,'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1608248597279-f99d160bfcbc?auto=format&fit=crop&w=600&q=80',0,0,0,103,'active','2026-07-07 10:19:57'),('intima-wash','pH-Balanced Intimate Wash','IntimaCare','Sexual Wellness',15.00,NULL,NULL,'trusted',0.0,0,44,5,'balance','pH-balanced wash','Pharmacist-vetted and clinically-backed. pH-balanced wash from IntimaCare. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80',0,0,0,22,'active','2026-06-29 11:06:36'),('intima-wipes','Daily Freshness Wipes','IntimaCare','Sexual Wellness',9.00,NULL,NULL,'',0.0,0,30,5,'fresh','pH-friendly wipes',NULL,'https://images.unsplash.com/photo-1608248597279-f99d160bfcbc?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80',0,0,0,104,'active','2026-07-07 10:19:57'),('lipcare-balm','Tinted Lip Treatment Balm SPF 15','LipCare+','Makeup',12.00,NULL,NULL,'best',0.0,0,84,5,'tint','Tinted lip treatment','Pharmacist-vetted and clinically-backed. Tinted lip treatment from LipCare+. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80',0,0,0,10,'active','2026-06-29 11:06:36'),('lumiere-vitc','Vitamin C 15% Brightening Serum','Lumière Skin','Skincare',28.00,NULL,NULL,'derm',0.0,0,42,5,'glow','Brightening serum','Pharmacist-vetted and clinically-backed. Brightening serum from Lumière Skin. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',0,0,0,0,'active','2026-06-29 11:06:36'),('nocturna-retinol','Retinol 0.3% Renewal Night Serum','NocturnaLab','Skincare',34.00,NULL,NULL,'trusted',0.0,0,4,5,'renew','Overnight renewal','Pharmacist-vetted and clinically-backed. Overnight renewal from NocturnaLab. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',0,0,0,3,'active','2026-06-29 11:06:36'),('phbalance-dandruff','Anti-Dandruff Scalp Tonic','PHbalance','Haircare',20.00,NULL,NULL,'',0.0,0,30,5,'scalp','Soothing scalp tonic',NULL,'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80',0,0,0,106,'active','2026-07-07 10:19:57'),('phbalance-keratin','Keratin Smooth Conditioner','PHbalance','Haircare',19.00,NULL,NULL,'',0.0,0,30,5,'smooth','Keratin conditioner',NULL,'https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80',0,0,0,107,'active','2026-07-07 10:19:57'),('phbalance-scalp','Caffeine + Biotin Scalp Serum','PHbalance','Haircare',25.00,NULL,NULL,'derm',0.0,0,22,5,'scalp','Caffeine scalp serum','Pharmacist-vetted and clinically-backed. Caffeine scalp serum from PHbalance. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',0,0,0,19,'active','2026-06-29 11:06:36'),('phbalance-shampoo','Bond Repair Strengthening Shampoo','PHbalance','Haircare',21.00,NULL,NULL,'new',0.0,0,40,5,'repair','Bond repair','Pharmacist-vetted and clinically-backed. Bond repair from PHbalance. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',1,0,2,11,'active','2026-06-29 11:06:36'),('phbalance-volume','Volumizing Root Spray','PHbalance','Haircare',17.00,NULL,NULL,'',0.0,0,30,5,'volume','Root-lifting spray',NULL,'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',0,0,0,108,'active','2026-07-07 10:19:57'),('pureday-deo','Aluminium-Free Deodorant','PureDay','Personal Care',11.00,NULL,NULL,'ff',0.0,0,66,5,'fresh','Aluminium-free','Pharmacist-vetted and clinically-backed. Aluminium-free from PureDay. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80',0,0,0,20,'active','2026-06-29 11:06:36'),('puremarine-omega','Omega-3 1000mg Fish Oil','PureMarine','Wellness',23.00,NULL,NULL,'trusted',0.0,0,52,5,'omega','Omega-3 fish oil','Pharmacist-vetted and clinically-backed. Omega-3 fish oil from PureMarine. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',0,1,3,14,'active','2026-06-29 11:06:36'),('rosegold-blush','Soft Blush Cream Stick','RoseGoldCo','Makeup',17.00,NULL,NULL,'trend',0.0,0,31,5,'blush','Cream blush stick','Pharmacist-vetted and clinically-backed. Cream blush stick from RoseGoldCo. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',0,0,0,18,'active','2026-06-29 11:06:36'),('solheure-spf','Invisible Fluid Sunscreen SPF 50+ PA++++','SolHeure','Skincare',26.00,NULL,NULL,'new',0.0,0,50,5,'protect','Invisible SPF 50+','Pharmacist-vetted and clinically-backed. Invisible SPF 50+ from SolHeure. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',1,0,1,4,'active','2026-06-29 11:06:36'),('test-serum','Test Serum','Test Brand','Skincare',25.00,NULL,NULL,'',0.0,0,10,5,'','','','uploads/20260703-223118-c1fccaf9.avif','uploads/20260703-223118-13d15ec8.jpg',0,0,0,0,'active','2026-07-03 19:31:21'),('tinycare-cream','Baby Soothing Diaper Cream','TinyCare','Mom & Baby',13.00,NULL,NULL,'derm',0.0,0,58,5,'soothe','Baby diaper cream','Pharmacist-vetted and clinically-backed. Baby diaper cream from TinyCare. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',0,0,0,21,'active','2026-06-29 11:06:36'),('velours-tint','Skin Tint Glow Foundation SPF 30','VeloursBeauty','Makeup',29.00,NULL,NULL,'vegan',0.0,0,27,5,'tint','Skin tint SPF 30','Pharmacist-vetted and clinically-backed. Skin tint SPF 30 from VeloursBeauty. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=600&q=80',1,0,3,9,'active','2026-06-29 11:06:36'),('vitawell-cal','Calcium + Vitamin D3 Complex','VitaWell','Wellness',18.00,21.00,15,'',0.0,0,46,5,'bone','Calcium + D3','Pharmacist-vetted and clinically-backed. Calcium + D3 from VitaWell. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',0,0,0,13,'active','2026-06-29 11:06:36'),('vitawell-d3','Vitamin D3 2000 IU','VitaWell','Wellness',14.00,NULL,NULL,'best',0.0,0,90,5,'vitamin d','Daily vitamin D3','Pharmacist-vetted and clinically-backed. Daily vitamin D3 from VitaWell. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=600&q=80',0,1,1,12,'active','2026-06-29 11:06:36'),('vitawell-mag','VitaWell Sleep Magnesium','VitaWell','Wellness',20.00,NULL,NULL,'',0.0,0,30,5,'calm','Sleep & relaxation',NULL,'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80',0,0,0,110,'active','2026-07-07 10:19:57'),('vitawell-omega','VitaWell Omega-3 Complex','VitaWell','Wellness',23.00,NULL,NULL,'',0.0,0,30,5,'omega','Heart & brain support',NULL,'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80',0,0,0,109,'active','2026-07-07 10:19:57'),('vitawell-zinc','VitaWell Immune Zinc + C','VitaWell','Wellness',13.00,NULL,NULL,'',0.0,0,30,5,'immune','Daily immune defense',NULL,'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=600&q=80',0,0,0,111,'active','2026-07-07 10:19:57'),('zenwell-mag','Magnesium Glycinate Sleep + Calm','ZenWell','Wellness',20.00,NULL,NULL,'best',0.0,0,5,5,'calm','Sleep + calm','Pharmacist-vetted and clinically-backed. Sleep + calm from ZenWell. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.','https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?auto=format&fit=crop&w=600&q=80','https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=600&q=80',0,1,2,16,'active','2026-06-29 11:06:36');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` varchar(64) NOT NULL,
  `author` varchar(120) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(160) DEFAULT '',
  `body` text NOT NULL,
  `status` enum('published','hidden') NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prod` (`product_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `skey` varchar(80) NOT NULL,
  `sval` text DEFAULT NULL,
  `sgroup` varchar(40) NOT NULL DEFAULT 'general',
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('announce_1','FREE SHIPPING on orders above $49','store'),('announce_2','Authentic Products • Expert Care • Secure Checkout','store'),('areeba_api_password','','payment'),('areeba_enabled','0','payment'),('areeba_gateway_url','https://epayment.areeba.com','payment'),('areeba_merchant_id','','payment'),('cod_enabled','1','payment'),('currency_label','$ USD','store'),('delivery_beirut_text','Beirut — same-day delivery','delivery'),('delivery_outside_text','Outside Beirut — 2-day delivery','delivery'),('footer_about','The online home of Well Pharmacy, Beirut — fusing real pharmacist expertise with clean, trend-forward beauty. Real results. Real confidence. Powered by science. Loved by you. ♡','store'),('free_ship_threshold','49','store'),('hero_eyebrow','clinically trusted','content'),('hero_sub','Real results. Real confidence. Powered by science, dispensed with care — your everyday glow, distilled. ♡','content'),('hero_title','next-gen','content'),('hero_title_accent','wellness','content'),('hours_status','Open now','hours'),('opening_hours','Mon – Sat | 9am – 9pm\r\nSunday | 11am – 6pm','hours'),('promise_accent','responsibly.','content'),('promise_line1','glow,','content'),('promise_sub','Beirut-born, science-led skincare & wellness — dispensed with the care of your neighborhood pharmacy, delivered to your door.','content'),('ship_fee_beirut','3','delivery'),('ship_fee_outside','5','delivery'),('social_facebook','','social'),('social_instagram','https://www.instagram.com/wellhealthandbeautyy','social'),('social_pinterest','','social'),('social_tiktok','https://www.tiktok.com/@wellhealthandbeauty','social'),('social_youtube','','social'),('store_address','Airport Road, before Al Aytam station, Beirut','store'),('store_email','hello@wellpharmacy.com','store'),('store_name','WELL SHOP','store'),('store_phone','+961 3 627 766','store'),('store_tagline','where Wellness meets You!','store'),('theme_cream','#EBE8DF','theme'),('theme_cream2','#E2DDD0','theme'),('theme_font_body','General Sans','theme'),('theme_font_display','Clash Display','theme'),('theme_ink','#2C261F','theme'),('theme_ink_soft','#4B3F35','theme'),('theme_primary','#9C8158','theme'),('theme_primary_deep','#7A6244','theme'),('theme_secondary','#9A6E3F','theme'),('theme_secondary_deep','#7E5730','theme'),('theme_star','#B59A5E','theme'),('whatsapp_number','9613627766','store');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscribers`
--

DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(160) NOT NULL,
  `source` varchar(40) DEFAULT 'popup',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscribers`
--

LOCK TABLES `subscribers` WRITE;
/*!40000 ALTER TABLE `subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscribers` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-07 14:00:09

WPDUMP;
$m->query('SET FOREIGN_KEY_CHECKS=0');
if ($m->multi_query($sql)) { do { if ($r = $m->store_result()) $r->free(); } while ($m->more_results() && $m->next_result()); }
$m->query('SET FOREIGN_KEY_CHECKS=1');
if ($m->errno) { http_response_code(500); echo 'IMPORT ERROR: ' . $m->error; }
else {
  $p = $m->query('SELECT COUNT(*) n FROM products'); $p = $p ? $p->fetch_assoc()['n'] : '?';
  $s = $m->query('SELECT COUNT(*) n FROM home_sections'); $s = $s ? $s->fetch_assoc()['n'] : '?';
  echo 'IMPORT OK - online DB now matches local. products=' . $p . ', home_sections=' . $s . '. Now tell Claude to delete this file.';
}
$m->close();
