<?php
declare(strict_types=1);

/**
Fields Summary:
- label [input]
- field [indexFieldSelection]
- scriptPath [input]
*/

namespace Pimcore\Model\DataObject\Fieldcollection\Data;

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\PreGetValueHookInterface;

class FilterSelect extends \Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractFilterDefinitionType
{
protected string $type = "FilterSelect";
protected ?string $label;
protected ?\Pimcore\Bundle\EcommerceFrameworkBundle\CoreExtensions\ObjectData\IndexFieldSelection $field;
protected ?string $scriptPath;


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
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterSelect
*/
public function setLabel(?string $label): static
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
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterSelect
*/
public function setField(?\Pimcore\Bundle\EcommerceFrameworkBundle\CoreExtensions\ObjectData\IndexFieldSelection $field): static
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
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\FilterSelect
*/
public function setScriptPath(?string $scriptPath): static
{
	$this->scriptPath = $scriptPath;

	return $this;
}

}

