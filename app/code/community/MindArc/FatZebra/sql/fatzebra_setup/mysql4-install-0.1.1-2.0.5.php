<?php

$installer = $this;
$installer->startSetup();

$installer->run("
DROP TABLE IF EXISTS {$this->getTable('fatzebrafraud_data')};
CREATE TABLE {$this->getTable('fatzebrafraud_data')} (
  `entity_id` int(10) NOT NULL auto_increment,
  `order_id` int(10) NOT NULL,
  `fraud_result` text NULL,
  `fraud_messages_title` text NULL,
  `fraud_messages_detail` text NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY  (`entity_id`),
  KEY `order_id_idx` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();