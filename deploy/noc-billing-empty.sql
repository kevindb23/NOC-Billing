-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: noc_billing
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.22.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acs_servers`
--

DROP TABLE IF EXISTS `acs_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acs_servers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_url` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_password` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `transport` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cwmp',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `ssh_username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssh_password` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssh_port` smallint unsigned NOT NULL DEFAULT '22',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `last_ssh_tested_at` timestamp NULL DEFAULT NULL,
  `last_api_tested_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acs_servers_public_id_unique` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activation_presets`
--

DROP TABLE IF EXISTS `activation_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activation_presets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dba_profile_id` bigint unsigned DEFAULT NULL,
  `ont_line_profile_id` bigint unsigned DEFAULT NULL,
  `ont_service_profile_id` bigint unsigned DEFAULT NULL,
  `ont_wan_profile_id` bigint unsigned DEFAULT NULL,
  `ont_tr069_server_profile_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activation_presets_olt_id_name_unique` (`olt_id`,`name`),
  UNIQUE KEY `activation_presets_public_id_unique` (`public_id`),
  KEY `activation_presets_dba_profile_id_foreign` (`dba_profile_id`),
  KEY `activation_presets_ont_line_profile_id_foreign` (`ont_line_profile_id`),
  KEY `activation_presets_ont_service_profile_id_foreign` (`ont_service_profile_id`),
  KEY `activation_presets_ont_wan_profile_id_foreign` (`ont_wan_profile_id`),
  KEY `activation_presets_ont_tr069_server_profile_id_foreign` (`ont_tr069_server_profile_id`),
  CONSTRAINT `activation_presets_dba_profile_id_foreign` FOREIGN KEY (`dba_profile_id`) REFERENCES `olt_dba_profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activation_presets_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activation_presets_ont_line_profile_id_foreign` FOREIGN KEY (`ont_line_profile_id`) REFERENCES `olt_qinq_provisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activation_presets_ont_service_profile_id_foreign` FOREIGN KEY (`ont_service_profile_id`) REFERENCES `olt_ont_service_profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activation_presets_ont_tr069_server_profile_id_foreign` FOREIGN KEY (`ont_tr069_server_profile_id`) REFERENCES `olt_ont_tr069_server_profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activation_presets_ont_wan_profile_id_foreign` FOREIGN KEY (`ont_wan_profile_id`) REFERENCES `olt_ont_wan_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activations`
--

DROP TABLE IF EXISTS `activations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscriber_service_id` bigint unsigned NOT NULL,
  `subscription_id` bigint unsigned NOT NULL,
  `olt_id` bigint unsigned DEFAULT NULL,
  `activation_preset_id` bigint unsigned DEFAULT NULL,
  `vlan_provision_id` bigint unsigned DEFAULT NULL,
  `qinq_provision_id` bigint unsigned DEFAULT NULL,
  `provisioning_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ont_id` bigint unsigned NOT NULL,
  `c_vlan` smallint unsigned DEFAULT NULL,
  `s_vlan` smallint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activated_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activations_public_id_unique` (`public_id`),
  KEY `activations_subscription_id_foreign` (`subscription_id`),
  KEY `activations_subscriber_service_id_status_index` (`subscriber_service_id`,`status`),
  KEY `activations_ont_id_status_index` (`ont_id`,`status`),
  KEY `activations_status_index` (`status`),
  KEY `activations_activation_preset_id_foreign` (`activation_preset_id`),
  KEY `activations_vlan_provision_id_foreign` (`vlan_provision_id`),
  KEY `activations_qinq_provision_id_foreign` (`qinq_provision_id`),
  KEY `activations_olt_id_status_index` (`olt_id`,`status`),
  CONSTRAINT `activations_activation_preset_id_foreign` FOREIGN KEY (`activation_preset_id`) REFERENCES `activation_presets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activations_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `activations_ont_id_foreign` FOREIGN KEY (`ont_id`) REFERENCES `onts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `activations_qinq_provision_id_foreign` FOREIGN KEY (`qinq_provision_id`) REFERENCES `olt_qinq_provisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activations_subscriber_service_id_foreign` FOREIGN KEY (`subscriber_service_id`) REFERENCES `subscriber_services` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `activations_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `activations_vlan_provision_id_foreign` FOREIGN KEY (`vlan_provision_id`) REFERENCES `olt_vlan_provisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `adjustments`
--

DROP TABLE IF EXISTS `adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_account_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `adjustment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adjustments_billing_account_id_foreign` (`billing_account_id`),
  KEY `adjustments_invoice_id_foreign` (`invoice_id`),
  KEY `adjustments_approved_by_foreign` (`approved_by`),
  CONSTRAINT `adjustments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `adjustments_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `adjustments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `correlation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `audit_logs_correlation_id_index` (`correlation_id`),
  CONSTRAINT `audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `balance_transactions`
--

DROP TABLE IF EXISTS `balance_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `balance_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_account_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `credit_id` bigint unsigned DEFAULT NULL,
  `adjustment_id` bigint unsigned DEFAULT NULL,
  `transaction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `balance_transactions_billing_account_id_foreign` (`billing_account_id`),
  KEY `balance_transactions_invoice_id_foreign` (`invoice_id`),
  KEY `balance_transactions_payment_id_foreign` (`payment_id`),
  KEY `balance_transactions_credit_id_foreign` (`credit_id`),
  KEY `balance_transactions_adjustment_id_foreign` (`adjustment_id`),
  KEY `balance_transactions_created_by_foreign` (`created_by`),
  CONSTRAINT `balance_transactions_adjustment_id_foreign` FOREIGN KEY (`adjustment_id`) REFERENCES `adjustments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `balance_transactions_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `balance_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `balance_transactions_credit_id_foreign` FOREIGN KEY (`credit_id`) REFERENCES `credits` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `balance_transactions_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `balance_transactions_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_accounts`
--

DROP TABLE IF EXISTS `billing_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `credit_limit_minor` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_accounts_public_id_unique` (`public_id`),
  UNIQUE KEY `billing_accounts_account_number_unique` (`account_number`),
  KEY `billing_accounts_status_index` (`status`),
  KEY `billing_accounts_customer_id_fk` (`customer_id`),
  CONSTRAINT `billing_accounts_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_cycles`
--

DROP TABLE IF EXISTS `billing_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_cycles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interval_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month',
  `interval_count` smallint unsigned NOT NULL DEFAULT '1',
  `billing_day` tinyint unsigned NOT NULL DEFAULT '1',
  `grace_days` smallint unsigned NOT NULL DEFAULT '7',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_cycles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_statements`
--

DROP TABLE IF EXISTS `billing_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_statements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_account_id` bigint unsigned NOT NULL,
  `subscription_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statement_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `billing_period_start` date DEFAULT NULL,
  `billing_period_end` date DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `subtotal_minor` bigint unsigned NOT NULL DEFAULT '0',
  `tax_minor` bigint unsigned NOT NULL DEFAULT '0',
  `total_minor` bigint unsigned NOT NULL DEFAULT '0',
  `amount_paid_minor` bigint unsigned NOT NULL DEFAULT '0',
  `balance_due_minor` bigint unsigned NOT NULL DEFAULT '0',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_statements_public_id_unique` (`public_id`),
  UNIQUE KEY `uniq_billing_statements_7fb1b4b95e` (`statement_number`),
  UNIQUE KEY `billing_statements_invoice_id_unique` (`invoice_id`),
  KEY `billing_statements_billing_account_id_foreign` (`billing_account_id`),
  KEY `billing_statements_subscription_id_foreign` (`subscription_id`),
  KEY `billing_statements_status_index` (`status`),
  CONSTRAINT `billing_statements_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `billing_statements_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `billing_statements_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_cgnat_policies`
--

DROP TABLE IF EXISTS `bng_cgnat_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_cgnat_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bng_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscriber_network` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscriber_interface` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internet_interface` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_ip_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'range',
  `public_ip_start` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_ip_end` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local_bypass_network` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_cgnat_policies_public_id_unique` (`public_id`),
  KEY `bng_cgnat_policies_bng_id_foreign` (`bng_id`),
  CONSTRAINT `bng_cgnat_policies_bng_id_foreign` FOREIGN KEY (`bng_id`) REFERENCES `bngs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_forwarding_rules`
--

DROP TABLE IF EXISTS `bng_forwarding_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_forwarding_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bng_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_interface` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internet_interface` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_forwarding_rules_public_id_unique` (`public_id`),
  KEY `bng_forwarding_rules_bng_id_foreign` (`bng_id`),
  CONSTRAINT `bng_forwarding_rules_bng_id_foreign` FOREIGN KEY (`bng_id`) REFERENCES `bngs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_radius_servers`
--

DROP TABLE IF EXISTS `bng_radius_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_radius_servers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bng_id` bigint unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `server_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `database_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `database_username` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `database_password` text COLLATE utf8mb4_unicode_ci,
  `sync_subscribers` tinyint(1) NOT NULL DEFAULT '0',
  `auth_port` smallint unsigned NOT NULL DEFAULT '1812',
  `accounting_port` smallint unsigned NOT NULL DEFAULT '1813',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_radius_servers_public_id_unique` (`public_id`),
  KEY `bng_radius_servers_bng_id_foreign` (`bng_id`),
  CONSTRAINT `bng_radius_servers_bng_id_foreign` FOREIGN KEY (`bng_id`) REFERENCES `bngs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_speed_boost_syncs`
--

DROP TABLE IF EXISTS `bng_speed_boost_syncs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_speed_boost_syncs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bng_speed_boost_id` bigint unsigned NOT NULL,
  `username` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_value` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_speed_boost_syncs_bng_speed_boost_id_username_unique` (`bng_speed_boost_id`,`username`),
  KEY `bng_speed_boost_syncs_username_index` (`username`),
  CONSTRAINT `bng_speed_boost_syncs_bng_speed_boost_id_foreign` FOREIGN KEY (`bng_speed_boost_id`) REFERENCES `bng_speed_boosts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_speed_boosts`
--

DROP TABLE IF EXISTS `bng_speed_boosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_speed_boosts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bng_id` bigint unsigned NOT NULL,
  `bng_radius_server_id` bigint unsigned NOT NULL,
  `plan_id` bigint unsigned NOT NULL,
  `download_kbps` int unsigned NOT NULL,
  `upload_kbps` int unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `last_applied_at` timestamp NULL DEFAULT NULL,
  `synced_user_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_speed_boosts_bng_id_bng_radius_server_id_plan_id_unique` (`bng_id`,`bng_radius_server_id`,`plan_id`),
  UNIQUE KEY `bng_speed_boosts_public_id_unique` (`public_id`),
  KEY `bng_speed_boosts_bng_radius_server_id_foreign` (`bng_radius_server_id`),
  KEY `bng_speed_boosts_plan_id_foreign` (`plan_id`),
  KEY `bng_speed_boosts_status_index` (`status`),
  CONSTRAINT `bng_speed_boosts_bng_id_foreign` FOREIGN KEY (`bng_id`) REFERENCES `bngs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bng_speed_boosts_bng_radius_server_id_foreign` FOREIGN KEY (`bng_radius_server_id`) REFERENCES `bng_radius_servers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bng_speed_boosts_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_vlan_interfaces`
--

DROP TABLE IF EXISTS `bng_vlan_interfaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_vlan_interfaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bng_id` bigint unsigned NOT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `vlan_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `outer_vlan` smallint unsigned DEFAULT NULL,
  `inner_vlan` smallint unsigned DEFAULT NULL,
  `interface_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_vlan_interfaces_bng_id_olt_id_interface_name_unique` (`bng_id`,`olt_id`,`interface_name`),
  KEY `bng_vlan_interfaces_olt_id_foreign` (`olt_id`),
  KEY `bng_vlan_interfaces_bng_id_olt_id_status_index` (`bng_id`,`olt_id`,`status`),
  CONSTRAINT `bng_vlan_interfaces_bng_id_foreign` FOREIGN KEY (`bng_id`) REFERENCES `bngs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bng_vlan_interfaces_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bng_vlan_syncs`
--

DROP TABLE IF EXISTS `bng_vlan_syncs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bng_vlan_syncs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bng_id` bigint unsigned NOT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `vlan_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bng_vlan_syncs_bng_id_olt_id_vlan_mode_unique` (`bng_id`,`olt_id`,`vlan_mode`),
  UNIQUE KEY `bng_vlan_syncs_public_id_unique` (`public_id`),
  KEY `bng_vlan_syncs_olt_id_foreign` (`olt_id`),
  CONSTRAINT `bng_vlan_syncs_bng_id_foreign` FOREIGN KEY (`bng_id`) REFERENCES `bngs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bng_vlan_syncs_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bngs`
--

DROP TABLE IF EXISTS `bngs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bngs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `management_endpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferred_transport` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ssh',
  `ssh_username` text COLLATE utf8mb4_unicode_ci,
  `ssh_password` text COLLATE utf8mb4_unicode_ci,
  `session_requested` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `parent_interface` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `egress_interface` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bngs_public_id_unique` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credits`
--

DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_account_id` bigint unsigned NOT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `remaining_minor` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credits_billing_account_id_foreign` (`billing_account_id`),
  CONSTRAINT `credits_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'residential',
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_username` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_password` text COLLATE utf8mb4_unicode_ci,
  `ppp_username` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ppp_password` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_public_id_unique` (`public_id`),
  UNIQUE KEY `customers_customer_number_unique` (`customer_number`),
  KEY `customers_status_index` (`status`),
  KEY `idx_customers_233177e306` (`legal_name`),
  KEY `idx_customers_0c83f57c78` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gcash_manual_payments`
--

DROP TABLE IF EXISTS `gcash_manual_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gcash_manual_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `statement_id` bigint unsigned DEFAULT NULL,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `transferred_on` date NOT NULL,
  `receipt_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_gcash_manual_payments_515c508429` (`reference_number`),
  KEY `gcash_manual_payments_user_id_foreign` (`user_id`),
  KEY `gcash_manual_payments_invoice_id_foreign` (`invoice_id`),
  KEY `gcash_manual_payments_reviewed_by_foreign` (`reviewed_by`),
  KEY `gcash_manual_payments_reference_number_index` (`reference_number`),
  KEY `gcash_manual_payments_status_index` (`status`),
  KEY `gcash_manual_payments_statement_id_foreign` (`statement_id`),
  CONSTRAINT `gcash_manual_payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `gcash_manual_payments_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gcash_manual_payments_statement_id_foreign` FOREIGN KEY (`statement_id`) REFERENCES `billing_statements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gcash_manual_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `subscription_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `unit_amount_minor` bigint unsigned NOT NULL,
  `line_total_minor` bigint unsigned NOT NULL,
  `tax_minor` bigint unsigned NOT NULL DEFAULT '0',
  `service_period_start` date DEFAULT NULL,
  `service_period_end` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  KEY `invoice_items_subscription_id_foreign` (`subscription_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoice_items_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_account_id` bigint unsigned NOT NULL,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `billing_period_start` date DEFAULT NULL,
  `billing_period_end` date DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `subtotal_minor` bigint unsigned NOT NULL DEFAULT '0',
  `discount_minor` bigint unsigned NOT NULL DEFAULT '0',
  `tax_minor` bigint unsigned NOT NULL DEFAULT '0',
  `total_minor` bigint unsigned NOT NULL DEFAULT '0',
  `amount_paid_minor` bigint unsigned NOT NULL DEFAULT '0',
  `balance_due_minor` bigint unsigned NOT NULL DEFAULT '0',
  `voided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_public_id_unique` (`public_id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  UNIQUE KEY `uniq_invoices_76701c3c73` (`billing_account_id`,`billing_period_start`,`billing_period_end`),
  KEY `invoices_status_index` (`status`),
  KEY `invoices_due_date_index` (`due_date`),
  CONSTRAINT `invoices_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `number_sequences`
--

DROP TABLE IF EXISTS `number_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `number_sequences` (
  `sequence_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_value` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`sequence_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_dba_profiles`
--

DROP TABLE IF EXISTS `olt_dba_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_dba_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `profile_id` smallint unsigned NOT NULL,
  `bandwidth_mbps` int unsigned NOT NULL,
  `profile_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_dba_profiles_olt_id_profile_id_unique` (`olt_id`,`profile_id`),
  CONSTRAINT `olt_dba_profiles_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_ont_service_profiles`
--

DROP TABLE IF EXISTS `olt_ont_service_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_ont_service_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `profile_id` int unsigned NOT NULL,
  `profile_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `eth_port_count` tinyint unsigned NOT NULL DEFAULT '1',
  `port_modes` json NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_ont_service_profiles_olt_id_profile_id_unique` (`olt_id`,`profile_id`),
  CONSTRAINT `olt_ont_service_profiles_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_ont_tr069_server_profiles`
--

DROP TABLE IF EXISTS `olt_ont_tr069_server_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_ont_tr069_server_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `profile_id` smallint unsigned NOT NULL,
  `profile_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_ont_tr069_server_profiles_olt_id_profile_id_unique` (`olt_id`,`profile_id`),
  CONSTRAINT `olt_ont_tr069_server_profiles_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_ont_wan_profiles`
--

DROP TABLE IF EXISTS `olt_ont_wan_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_ont_wan_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `profile_id` tinyint unsigned NOT NULL,
  `profile_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nat_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_ont_wan_profiles_olt_id_profile_id_unique` (`olt_id`,`profile_id`),
  CONSTRAINT `olt_ont_wan_profiles_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_qinq_provisions`
--

DROP TABLE IF EXISTS `olt_qinq_provisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_qinq_provisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `outer_vlan` smallint unsigned DEFAULT NULL,
  `inner_vlan` smallint unsigned DEFAULT NULL,
  `qinq_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's_vlan',
  `service_port_id` int unsigned DEFAULT NULL,
  `frame` smallint unsigned DEFAULT NULL,
  `slot` smallint unsigned DEFAULT NULL,
  `port_number` smallint unsigned DEFAULT NULL,
  `ont_line_profile` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_id` int unsigned DEFAULT NULL,
  `dba_profile_id` int unsigned DEFAULT NULL,
  `tr069_management_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `tr069_ip_index` tinyint unsigned NOT NULL DEFAULT '1',
  `omcc_encrypt_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `port` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_qinq_provisions_olt_id_outer_vlan_inner_vlan_unique` (`olt_id`,`outer_vlan`,`inner_vlan`),
  CONSTRAINT `olt_qinq_provisions_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_terminal_users`
--

DROP TABLE IF EXISTS `olt_terminal_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_terminal_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_name` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'root',
  `password` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `privilege_level` tinyint unsigned NOT NULL DEFAULT '3',
  `reenter_limit` tinyint unsigned NOT NULL DEFAULT '1',
  `appended_info` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_terminal_users_olt_id_username_unique` (`olt_id`,`username`),
  CONSTRAINT `olt_terminal_users_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olt_vlan_provisions`
--

DROP TABLE IF EXISTS `olt_vlan_provisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_vlan_provisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `vlan_id` smallint unsigned NOT NULL,
  `vlan_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'smart',
  `vlan_to` smallint unsigned DEFAULT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internet',
  `frame` tinyint unsigned DEFAULT NULL,
  `slot` tinyint unsigned DEFAULT NULL,
  `port_number` tinyint unsigned DEFAULT NULL,
  `port` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_vlan_provisions_olt_id_vlan_id_unique` (`olt_id`,`vlan_id`),
  CONSTRAINT `olt_vlan_provisions_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olts`
--

DROP TABLE IF EXISTS `olts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `management_endpoint` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferred_transport` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ssh',
  `ssh_username` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssh_password` text COLLATE utf8mb4_unicode_ci,
  `session_requested` tinyint(1) NOT NULL DEFAULT '0',
  `dba_profile_start_id` smallint unsigned NOT NULL DEFAULT '10',
  `ont_service_profile_start_id` smallint unsigned NOT NULL DEFAULT '0',
  `ont_wan_profile_start_id` tinyint unsigned NOT NULL DEFAULT '0',
  `ont_tr069_profile_start_id` tinyint unsigned NOT NULL DEFAULT '1',
  `ont_line_profile_start_id` smallint unsigned NOT NULL DEFAULT '0',
  `s_vlan_start_id` smallint unsigned NOT NULL DEFAULT '1',
  `c_vlan_start_id` smallint unsigned NOT NULL DEFAULT '1',
  `tr069_vlan_start_id` smallint unsigned NOT NULL DEFAULT '1',
  `vlan_start_id` smallint unsigned NOT NULL DEFAULT '1',
  `ont_id_capacity_per_port` smallint unsigned NOT NULL DEFAULT '64',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `terminal_user_security_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `terminal_user_security_length` tinyint unsigned NOT NULL DEFAULT '12',
  PRIMARY KEY (`id`),
  UNIQUE KEY `olts_public_id_unique` (`public_id`),
  KEY `olts_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ont_settings`
--

DROP TABLE IF EXISTS `ont_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ont_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `do_not_allow_rogue_onus` tinyint(1) NOT NULL DEFAULT '0',
  `ont_id_capacity_per_port` smallint unsigned NOT NULL DEFAULT '64',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `onts`
--

DROP TABLE IF EXISTS `onts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `frame` smallint unsigned NOT NULL,
  `slot` smallint unsigned NOT NULL,
  `pon_port` smallint unsigned NOT NULL,
  `ont_id` smallint unsigned DEFAULT NULL,
  `serial_number` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `last_discovered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `acs_server_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onts_olt_id_serial_number_unique` (`olt_id`,`serial_number`),
  UNIQUE KEY `onts_public_id_unique` (`public_id`),
  KEY `onts_status_index` (`status`),
  KEY `onts_acs_server_id_foreign` (`acs_server_id`),
  CONSTRAINT `onts_acs_server_id_foreign` FOREIGN KEY (`acs_server_id`) REFERENCES `acs_servers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `onts_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `onus`
--

DROP TABLE IF EXISTS `onus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `purchase_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onus_public_id_unique` (`public_id`),
  UNIQUE KEY `onus_serial_number_unique` (`serial_number`),
  KEY `onus_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_billing_settings`
--

DROP TABLE IF EXISTS `organization_billing_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_billing_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_start_day` tinyint unsigned NOT NULL DEFAULT '20',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '12.00',
  `installation_amortization_months` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_brandings`
--

DROP TABLE IF EXISTS `organization_brandings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_brandings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_mark` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tagline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accent_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_email_settings`
--

DROP TABLE IF EXISTS `organization_email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_email_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `port` smallint unsigned NOT NULL DEFAULT '587',
  `encryption` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tls',
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_organization_email_setti_e8701ad48b` (`user_id`),
  CONSTRAINT `organization_email_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_gcash_settings`
--

DROP TABLE IF EXISTS `organization_gcash_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_gcash_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_organization_gcash_setti_e8701ad48b` (`user_id`),
  CONSTRAINT `organization_gcash_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_notification_settings`
--

DROP TABLE IF EXISTS `organization_notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `telegram_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `telegram_bot_token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `telegram_chat_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_organization_notificatio_e8701ad48b` (`user_id`),
  CONSTRAINT `organization_notification_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_paymongo_settings`
--

DROP TABLE IF EXISTS `organization_paymongo_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_paymongo_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `environment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'test',
  `public_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secret_key` text COLLATE utf8mb4_unicode_ci,
  `webhook_secret` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_organization_paymongo_se_e8701ad48b` (`user_id`),
  CONSTRAINT `organization_paymongo_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_allocations`
--

DROP TABLE IF EXISTS `payment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payment_allocations_ee1ab1bc83` (`payment_id`,`invoice_id`),
  KEY `payment_allocations_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `payment_allocations_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_allocations_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_account_id` bigint unsigned NOT NULL,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `received_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_public_id_unique` (`public_id`),
  UNIQUE KEY `uniq_payments_a691039c35` (`payment_number`),
  UNIQUE KEY `uniq_payments_4824938f14` (`idempotency_key`),
  KEY `payments_billing_account_id_foreign` (`billing_account_id`),
  KEY `payments_status_index` (`status`),
  CONSTRAINT `payments_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plan_versions`
--

DROP TABLE IF EXISTS `plan_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `recurring_price_minor` bigint unsigned NOT NULL,
  `setup_fee_minor` bigint unsigned NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `download_kbps` bigint unsigned NOT NULL,
  `upload_kbps` bigint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_versions_plan_id_version_unique` (`plan_id`,`version`),
  CONSTRAINT `plan_versions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_cycle_id` bigint unsigned NOT NULL,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internet',
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_public_id_unique` (`public_id`),
  UNIQUE KEY `plans_code_unique` (`code`),
  KEY `plans_billing_cycle_id_foreign` (`billing_cycle_id`),
  KEY `plans_status_index` (`status`),
  CONSTRAINT `plans_billing_cycle_id_foreign` FOREIGN KEY (`billing_cycle_id`) REFERENCES `billing_cycles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_assignments`
--

DROP TABLE IF EXISTS `role_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_assignments_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `role_assignments_role_id_foreign` (`role_id`),
  CONSTRAINT `role_assignments_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `routers`
--

DROP TABLE IF EXISTS `routers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `routers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Other',
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `management_endpoint` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferred_transport` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssh_username` text COLLATE utf8mb4_unicode_ci,
  `ssh_password` text COLLATE utf8mb4_unicode_ci,
  `session_requested` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `routers_public_id_unique` (`public_id`),
  KEY `routers_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriber_services`
--

DROP TABLE IF EXISTS `subscriber_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriber_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `billing_account_id` bigint unsigned NOT NULL,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internet',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `activated_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `terminated_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriber_services_public_id_unique` (`public_id`),
  UNIQUE KEY `subscriber_services_service_number_unique` (`service_number`),
  KEY `subscriber_services_billing_account_id_foreign` (`billing_account_id`),
  KEY `subscriber_services_status_index` (`status`),
  KEY `subscriber_services_customer_id_fk` (`customer_id`),
  CONSTRAINT `subscriber_services_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `subscriber_services_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscriber_service_id` bigint unsigned NOT NULL,
  `billing_account_id` bigint unsigned NOT NULL,
  `plan_version_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `starts_on` date NOT NULL,
  `ends_on` date DEFAULT NULL,
  `next_billing_date` date NOT NULL,
  `billing_day` tinyint unsigned NOT NULL DEFAULT '1',
  `price_snapshot_minor` bigint unsigned NOT NULL,
  `currency_snapshot` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_subscriber_service_id_foreign` (`subscriber_service_id`),
  KEY `subscriptions_billing_account_id_foreign` (`billing_account_id`),
  KEY `subscriptions_plan_version_id_foreign` (`plan_version_id`),
  KEY `subscriptions_status_index` (`status`),
  KEY `subscriptions_next_billing_date_index` (`next_billing_date`),
  CONSTRAINT `subscriptions_billing_account_id_foreign` FOREIGN KEY (`billing_account_id`) REFERENCES `billing_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `subscriptions_plan_version_id_foreign` FOREIGN KEY (`plan_version_id`) REFERENCES `plan_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `subscriptions_subscriber_service_id_foreign` FOREIGN KEY (`subscriber_service_id`) REFERENCES `subscriber_services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_public_id_unique` (`public_id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'noc_billing'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-21  7:25:11
-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: noc_billing
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.22.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2026_09_09_000000_create_billing_schema',1),(6,'2026_09_11_000004_restore_customer_schema',2),(7,'2026_09_11_000005_restore_customer_foreign_keys',3),(8,'2026_09_11_000006_create_organization_brandings_table',4),(9,'2026_09_12_000007_provision_administrator_access',4),(10,'2026_09_12_000008_create_organization_email_settings_table',5),(11,'2026_09_12_000009_create_organization_notification_settings_table',6),(12,'2026_09_12_000010_add_organization_id_to_personal_access_tokens',7),(13,'2026_09_12_000011_create_organization_paymongo_settings_table',8),(14,'2026_09_12_000012_create_organization_gcash_settings_table',9),(15,'2026_09_12_000013_create_gcash_manual_payments_table',9),(16,'2026_09_13_000014_create_organization_billing_settings_table',10),(17,'2026_09_13_000015_set_full_installation_payment_default',11),(18,'2026_09_13_000016_create_billing_statements_table',12),(19,'2026_09_13_000017_update_gcash_payments_for_statements',13),(20,'2026_09_13_000018_remove_organization_scoping',14),(21,'2026_09_13_000019_create_number_sequences_table',15),(29,'2026_09_14_000001_create_routers_table',16),(30,'2026_09_14_000002_add_ssh_credentials_to_routers_table',17),(31,'2026_09_14_000003_create_olts_table',18),(32,'2026_09_14_000004_add_preferred_transport_to_olts_table',19),(33,'2026_09_15_000001_create_olt_provisioning_tables',20),(34,'2026_09_15_000002_add_ssh_credentials_to_olts_table',21),(35,'2026_09_16_000001_add_qinq_type_to_olt_qinq_provisions',22),(36,'2026_09_16_000002_make_qinq_vlans_nullable',23),(37,'2026_09_16_000003_add_huawei_profile_fields',24),(38,'2026_09_16_000004_add_olt_session_requested',25),(39,'2026_09_16_000005_add_huawei_undo_details',26),(40,'2026_09_16_000006_add_port_details_to_olt_vlan_provisions',27),(41,'2026_09_16_000007_create_olt_dba_profiles_table',28),(42,'2026_09_16_000008_add_dba_profile_start_id_to_olts_table',29),(43,'2026_09_16_000009_add_router_session_requested',30),(44,'2026_09_17_000001_create_bngs_table',31),(45,'2026_09_17_000002_add_bng_interfaces',32),(46,'2026_09_17_000003_create_bng_cgnat_policies_table',33),(47,'2026_09_17_000004_create_bng_forwarding_rules_table',34),(48,'2026_09_18_000001_create_bng_radius_servers_table',35),(49,'2026_09_18_000002_add_database_credentials_to_bng_radius_servers',36),(50,'2026_09_18_000003_add_subscriber_credentials_to_customers',37),(51,'2026_09_18_000004_create_bng_speed_boosts_table',38),(52,'2026_09_18_000005_create_bng_speed_boost_syncs_table',39),(53,'2026_09_18_000006_add_sync_subscribers_to_bng_radius_servers',40),(54,'2026_09_18_000007_encrypt_legacy_bng_radius_credentials',41),(55,'2026_09_18_000008_create_olt_ont_service_profiles_table',42),(56,'2026_09_18_000009_add_ont_service_profile_start_id_to_olts_table',43),(57,'2026_09_18_000010_create_olt_ont_wan_profiles_table',44),(58,'2026_09_19_000001_add_vlan_type_and_range_to_olt_vlan_provisions',45),(59,'2026_09_19_000002_create_olt_ont_tr069_server_profiles_table',45),(60,'2026_09_19_000003_create_onts_table',46),(61,'2026_09_19_000004_create_onus_table',47),(62,'2026_09_19_000005_create_ont_settings_table',48),(63,'2026_09_19_000006_create_olt_terminal_users_table',49),(64,'2026_09_19_000007_add_terminal_user_policy_to_olts_table',50),(65,'2026_09_19_000008_create_acs_servers_table',51),(66,'2026_09_19_000009_add_acs_server_id_to_onts_table',52),(67,'2026_09_19_000010_create_activations_table',53),(68,'2026_09_19_000011_create_activation_presets_and_link_activations',54),(69,'2026_09_20_000001_add_ont_wan_profile_start_id_to_olts_table',55),(70,'2026_09_20_000002_add_ont_tr069_profile_start_id_to_olts_table',56),(71,'2026_09_20_000003_add_ont_line_profile_start_id_to_olts_table',57),(72,'2026_09_20_000004_add_vlan_start_ids_to_olts_table',58),(73,'2026_09_20_000005_add_vlan_start_id_to_olts_table',59),(74,'2026_09_20_000006_add_ont_id_capacity_per_port_to_olts_table',60),(75,'2026_09_20_000007_add_ont_id_capacity_per_port_to_ont_settings_table',61),(76,'2026_09_20_000008_add_ont_line_profile_settings_to_qinq_provisions',62),(77,'2026_09_20_000009_create_bng_vlan_syncs_tables',63);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'audit-logs.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(2,'audit-logs.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(3,'billing.create','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(4,'billing.delete','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(5,'billing.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(6,'billing.update','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(7,'billing.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(8,'dashboard.create','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(9,'dashboard.delete','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(10,'dashboard.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(11,'dashboard.update','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(12,'dashboard.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(13,'network.create','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(14,'network.delete','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(15,'network.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(16,'network.update','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(17,'network.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(18,'roles.create','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(19,'roles.delete','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(20,'roles.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(21,'roles.update','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(22,'roles.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(23,'system.create','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(24,'system.delete','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(25,'system.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(26,'system.update','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(27,'system.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(28,'users.create','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(29,'users.delete','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(30,'users.export','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(31,'users.update','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(32,'users.view','api','2026-09-11 23:32:41','2026-09-11 23:32:41'),(33,'branding.view','api','2026-09-12 05:15:11','2026-09-12 05:15:11'),(34,'branding.update','api','2026-09-12 05:15:11','2026-09-12 05:15:11'),(35,'email.test','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(36,'email.update','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(37,'email.view','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(38,'api-tokens.create','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(39,'api-tokens.delete','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(40,'api-tokens.view','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(41,'notifications.test','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(42,'notifications.update','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(43,'notifications.view','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(44,'paymongo.test','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(45,'paymongo.update','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(46,'paymongo.view','api','2026-09-12 11:37:26','2026-09-12 11:37:26'),(55,'routers.view','api','2026-09-14 05:40:36','2026-09-14 05:40:36'),(56,'routers.create','api','2026-09-14 05:40:36','2026-09-14 05:40:36'),(57,'routers.update','api','2026-09-14 05:40:36','2026-09-14 05:40:36'),(58,'routers.delete','api','2026-09-14 05:40:36','2026-09-14 05:40:36'),(59,'olts.view','api','2026-09-14 17:57:00','2026-09-14 17:57:00'),(60,'olts.create','api','2026-09-14 17:57:00','2026-09-14 17:57:00'),(61,'olts.update','api','2026-09-14 17:57:00','2026-09-14 17:57:00'),(62,'olts.delete','api','2026-09-14 17:57:00','2026-09-14 17:57:00'),(63,'olts.manage','api','2026-09-15 14:15:36','2026-09-15 14:15:36'),(64,'olts.provision','api','2026-09-15 14:15:36','2026-09-15 14:15:36'),(65,'acs.create','api','2026-09-19 08:38:42','2026-09-19 08:38:42'),(66,'acs.delete','api','2026-09-19 08:38:42','2026-09-19 08:38:42'),(67,'acs.test','api','2026-09-19 08:38:42','2026-09-19 08:38:42'),(68,'acs.update','api','2026-09-19 08:38:42','2026-09-19 08:38:42'),(69,'acs.view','api','2026-09-19 08:38:42','2026-09-19 08:38:42');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','api','2026-09-11 23:32:41','2026-09-11 23:32:41');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `role_assignments`
--

LOCK TABLES `role_assignments` WRITE;
/*!40000 ALTER TABLE `role_assignments` DISABLE KEYS */;
INSERT INTO `role_assignments` VALUES (1,1,1,'2026-09-11 23:32:42','2026-09-11 23:32:42');
/*!40000 ALTER TABLE `role_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'01M2407KJZ5YABPF18YMK66EGP','Billing Administrator','admin@example.com',NULL,'$2y$12$GAR4tb9h9SxClxbkI.R5V.Q1GNV3oWOU0S0Wq4PdrHM6yQVphICoa','active',NULL,'2026-09-09 21:12:25','2026-09-10 02:46:05');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-21  7:25:11
