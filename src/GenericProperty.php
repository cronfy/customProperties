<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 06.04.18
 * Time: 14:31
 */

namespace cronfy\customProperties;

use cronfy\experience\yii2\activeModel\ActiveModel;
use cronfy\experience\yii2\ensureSave\EnsureSaveTrait;

/**
 * @property bool $isEmpty
 * @property bool $isExternal
 * @property mixed $value
 */
class GenericProperty extends ActiveModel
{
    use EnsureSaveTrait;

    public $sid;
    public $owner;

    public $propertyLabel;

    public function attributes()
    {
        return array_merge(
            parent::attributes(),
            [
                'value',
            ]
        );
    }

    public function attributeLabels()
    {
        return array_merge(
            parent::attributeLabels(),
            [
                'value' => $this->propertyLabel ?: static::class,
            ]
        );
    }

    public function rules()
    {
        return array_merge(
            parent::rules(),
            [
                'value/safe' => ['value', 'safe'],
            ]
        );
    }

    public function getIsEmpty()
    {
        return $this->attributes() === ['value'] && !$this->value;
    }

    public function getIsExternal()
    {
        return false;
    }

    protected $_loadScope;
    public function setLoadScope($value)
    {
        $this->_loadScope = $value;
    }

    /**
     * @return mixed
     */
    public function getLoadScope()
    {
        return $this->_loadScope;
    }

    protected function saveInternal($attributeNames)
    {
        // по умолчанию тут ничего не делаем, этодля тех методов, которые хранятся в БД отдельно
        return true;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }

        return $this->saveInternal($attributeNames);
    }
}
