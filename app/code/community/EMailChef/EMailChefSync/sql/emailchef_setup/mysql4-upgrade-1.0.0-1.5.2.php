<?php

$this->startSetup();

$this->run('CREATE TABLE IF NOT EXISTS `emailchef_fields_mapping` (
  `magento_field_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `emailchef_field_id` int(11) NOT NULL,
  PRIMARY KEY (`magento_field_name`, `emailchef_field_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');

$this->endSetup();
