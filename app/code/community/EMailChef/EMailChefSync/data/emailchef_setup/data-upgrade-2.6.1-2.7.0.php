<?php
/**
 * Update EMailChef.
 */
$installer = $this;
$this->startSetup();

/*
 * Rename sync jobs to remove sevenlike reference
 */
$cron_schedule_table = Mage::getSingleton('core/resource')->getTableName('cron_schedule');
$installer->run("UPDATE {$cron_schedule_table} SET job_code='emailchef_emailchefsync' WHERE job_code='sevenlike_emailchef'");

$this->endSetup();
