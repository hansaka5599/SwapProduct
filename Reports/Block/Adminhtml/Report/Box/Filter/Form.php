<?php

/**
 * Class Ecommistry_Reports_Block_Adminhtml_Report_Box_Filter_Form
 */
class Ecommistry_Reports_Block_Adminhtml_Report_Box_Filter_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * @var array
     */
    protected $_fieldVisibility             = array();

    /**
     * @var array
     */
    protected $_fieldOptions                = array();

    /**
     * @param $fieldId
     * @param $visibility
     * @return $this
     */
    public function setFieldVisibility($fieldId, $visibility)
    {
        $this->_fieldVisibility[$fieldId] = $visibility ? true : false;
        return $this;
    }

    /**
     * @param $fieldId
     * @param bool $defaultVisibility
     * @return bool|mixed
     */
    public function getFieldVisibility($fieldId, $defaultVisibility = true)
    {
        if (isset($this->_fieldVisibility[$fieldId])) {
            return $this->_fieldVisibility[$fieldId];
        }
        return $defaultVisibility;
    }

    /**
     * @param $fieldId
     * @param $option
     * @param null $value
     * @return $this
     */
    public function setFieldOption($fieldId, $option, $value = null)
    {
        if (is_array($option)) {
            $options    = $option;
        } else {
            $options    = array($option => $value);
        }

        if (!isset($this->_fieldOptions[$fieldId])) {
            $this->_fieldOptions[$fieldId] = array();
        }

        foreach ($options as $key => $value) {
            $this->_fieldOptions[$fieldId][$key] = $value;
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _prepareForm()
    {
        $actionUrl      = $this->getCurrentUrl();
        $form           = new Varien_Data_Form(array(
            'id'        => 'filter_form',
            'action'    => $actionUrl,
            'method'    => 'get'
        ));

        $htmlIdPrefix   = 'swap_products_';
        $form->setHtmlIdPrefix($htmlIdPrefix);

        $fieldset       = $form->addFieldset('base_fieldset', array('legend' => Mage::helper('ecommistry_reports')->__('Filter')));

        $fieldset->addField('old_sku', 'text', array(
            'name'      => 'old_sku',
            'required'  => true,
            'label'     => Mage::helper('ecommistry_reports')->__('Old SKU'),
            'title'     => Mage::helper('ecommistry_reports')->__('Old SKU')
        ));
        $fieldset->addField('new_sku', 'text', array(
            'name'      => 'new_sku',
            'required'  => false,
            'label'     => Mage::helper('ecommistry_reports')->__('New SKU'),
            'title'     => Mage::helper('ecommistry_reports')->__('New SKU')
        ));
        $fieldset->addField('process', 'hidden', array(
            'name'      => 'process',
            'required'  => false,
            'label'     => Mage::helper('ecommistry_reports')->__('Process'),
            'title'     => Mage::helper('ecommistry_reports')->__('Process')
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        return $this;
    }


    /**
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _initFormValues()
    {
        $filterData     = $this->getFilterData();
        $this->getForm()->addValues($filterData->getData());
        return parent::_initFormValues();
    }

    /**
     * @return mixed
     */
    protected function _beforeHtml()
    {
        $result         = parent::_beforeHtml();
        $elements       = $this->getForm()->getElements();

        foreach ($elements as $element) {
            $this->_applyFieldVisibiltyAndOptions($element);
        }

        return $result;
    }

    /**
     * @param $element
     * @return mixed
     */
    protected function _applyFieldVisibiltyAndOptions($element) {
        if ($element instanceof Varien_Data_Form_Element_Fieldset) {
            foreach ($element->getElements() as $fieldElement) {
                if ($fieldElement instanceof Varien_Data_Form_Element_Fieldset) {
                    $this->_applyFieldVisibiltyAndOptions($fieldElement);
                    continue;
                }

                $fieldId = $fieldElement->getId();

                if (!$this->getFieldVisibility($fieldId)) {
                    $element->removeField($fieldId);
                    continue;
                }

                if (isset($this->_fieldOptions[$fieldId])) {
                    $fieldOptions = $this->_fieldOptions[$fieldId];
                    foreach ($fieldOptions as $k => $v) {
                        $fieldElement->setDataUsingMethod($k, $v);
                    }
                }
            }
        }
        return $element;
    }
}