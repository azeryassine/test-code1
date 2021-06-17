<?php

/**
Fields Summary:
- label [input]
- field [indexFieldSelection]
- scriptPath [input]
- UseAndCondition [checkbox]
*/

namespace Pimcore\Model\DataObject\Fieldcollection\Data;

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\PreGetValueHookInterface;

class FilterMultiSelectFromMultiSelect extends \Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractFilterDefinitionType {

protected $type = "FilterMultiSelectFromMultiSelect";
protected $label;
protected $field;
protected $scriptPath;
protected $UseAndCondition;


/**
* Get label - Label
* @return string|null
*/
public function getLabel(): ?string
{
	$data = $this->label;
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set label - Label
* @param string|null $label
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterMultiSelectFromMultiSelect
*/
public function setLabel(?string $label)
{
	$this->label = $label;

	return $this;
}

/**
* Get field - Field
* @return \Pimcore\Bundle\EcommerceFrameworkBundle\CoreExtensions\ObjectData\IndexFieldSelection|null
*/
public function getField(): ?\Pimcore\Bundle\EcommerceFrameworkBundle\CoreExtensions\ObjectData\IndexFieldSelection
{
	$data = $this->field;
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set field - Field
* @param \Pimcore\Bundle\EcommerceFrameworkBundle\CoreExtensions\ObjectData\IndexFieldSelection|null $field
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterMultiSelectFromMultiSelect
*/
public function setField(?\Pimcore\Bundle\EcommerceFrameworkBundle\CoreExtensions\ObjectData\IndexFieldSelection $field)
{
	$this->field = $field;

	return $this;
}

/**
* Get scriptPath - Script Path
* @return string|null
*/
public function getScriptPath(): ?string
{
	$data = $this->scriptPath;
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set scriptPath - Script Path
* @param string|null $scriptPath
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterMultiSelectFromMultiSelect
*/
public function setScriptPath(?string $scriptPath)
{
	$this->scriptPath = $scriptPath;

	return $this;
}

/**
* Get UseAndCondition - Use And Condition
* @return bool|null
*/
public function getUseAndCondition(): ?bool
{
	$data = $this->UseAndCondition;
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set UseAndCondition - Use And Condition
* @param bool|null $UseAndCondition
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterMultiSelectFromMultiSelect
*/
public function setUseAndCondition(?bool $UseAndCondition)
{
	$this->UseAndCondition = $UseAndCondition;

	return $this;
}

}

