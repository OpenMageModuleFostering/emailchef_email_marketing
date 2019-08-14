<?php
/**
 * Grid.php.
 */
class EMailChef_EMailChefSync_Block_Adminhtml_EMailChef_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('EMailChefGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare Collection.
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('emailchef/job')->getCollection();
        $this->setCollection($collection);
        // Set default sort to ID by highest to lowest (normally shows most recent first)
        $this->setDefaultSort('id');
        $this->setDefaultDir('desc');

        //var_dump(Mage::getModel('emailchef/job')->load(1));

        return parent::_prepareCollection();
    }

    /**
     * Prepare Grid Columns.
     */
    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
          'header' => Mage::helper('emailchef')->__('ID'),
          //'align'     =>'right',
          'width' => '80px',
          'index' => 'id',
        ));

        $this->addColumn('type', array(
            'header' => Mage::helper('emailchef')->__('Type'),
            'align' => 'left',
            'index' => 'type',
            'type' => 'options',
            'options' => array(
                0 => 'Manual Sync',
                1 => 'Auto Sync',
                //2 => 'Disabled',
            ),
        ));

        $this->addColumn('store_id', array(
            'header' => Mage::helper('emailchef')->__('Store'),
            'align' => 'left',
            //'width'     => '150px',
            'index' => 'store_id',
            'type' => 'options',
            'options' => Mage::getModel('emailchef/source_store')->getSelectOptions(),
        ));

        $this->addColumn('emailchefgroupid', array(
            'header' => Mage::helper('emailchef')->__('eMailChef Group ID'),
            //'align'     =>'right',
            'width' => '80px',
            'index' => 'emailchefgroupid',
        ));

        $this->addColumn('list_id', array(
            'header' => Mage::helper('emailchef')->__('eMailChef List ID'),
            //'align'     =>'right',
            'width' => '80px',
            'index' => 'list_id',
        ));

        /*$this->addColumn('list_guid', array(
            'header' => Mage::helper('emailchef')->__('eMailChef List GUID'),
            'index' => 'list_guid',
        ));*/

        $this->addColumn('status', array(
            'header' => Mage::helper('emailchef')->__('Status'),
            //'align'     =>'right',
            'index' => 'status',
        ));
/*
        $this->addColumn('process_id', array(
            'header' => Mage::helper('emailchef')->__('Process ID'),
            //'align'     =>'right',
            'width' => '80px',
            'index' => 'process_id',
        ));
*/
        $this->addColumn('tries', array(
            'header' => Mage::helper('emailchef')->__('Tries'),
            //'align'     =>'right',
            'width' => '50px',
            'index' => 'tries',
        ));

        $this->addColumn('queue_datetime', array(
            'header' => Mage::helper('emailchef')->__('Queue Time'),
            'type' => 'datetime', // Add in Date Picker
            //'type'      => 'timestamp',
            //'align'     => 'center',
            'width' => '180px',
            'index' => 'queue_datetime',
            //'gmtoffset' => true
        ));

        $this->addColumn('start_datetime', array(
            'header' => Mage::helper('emailchef')->__('Started'),
            'type' => 'datetime', // Add in Date Picker
            //'type'      => 'timestamp',
            //'align'     => 'center',
            'width' => '180px',
            'index' => 'start_datetime',
            //'gmtoffset' => true
        ));

        $this->addColumn('finish_datetime', array(
            'header' => Mage::helper('emailchef')->__('Finished'),
            'type' => 'datetime', // Add in Date Picker
            //'type'      => 'timestamp',
            //'align'     => 'center',
            'width' => '180px',
            'index' => 'finish_datetime',
            //'gmtoffset' => true
        ));

        $this->addColumn('action',
            array(
                'header' => Mage::helper('emailchef')->__('Action'),
                'width' => '100',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('emailchef')->__('Run'),
                        'url' => array('base' => '*/*/runjob'),
                        'field' => 'id',
                    ),
                    array(
                        'caption' => Mage::helper('emailchef')->__('Delete'),
                        'url' => array('base' => '*/*/delete'),
                        'field' => 'id',
                    ),
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
        ));

        return parent::_prepareColumns();
    }

    /**
     * Get row url - None editable.
     */
    public function getRowUrl($row)
    {
        return '';
    }
}
