<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 06.04.18
 * Time: 13:13
 */

namespace cronfy\customProperties;

use yii\base\Arrayable;
use yii\base\BaseObject;

class CustomProperties extends BaseObject implements Arrayable
{
    public $owner;

    /**
     * @var array[]|GenericProperty[] содержит определенные на текущий момент свойства (НЕ ВСЕ возможные свойства)
     * Ключи - названия свойств, значение - либо массив, описывающий поля свойства, либо объект
     * свойства.
     *
     * Логика работы такая: в начале (при создании new CustomProperties()) сюда попадает сырой массив свойств
     * (через setDefinition($value)).
     *
     * Если при работе какое-то свойство не потребовалось, оно так и остается массивом и сохраняется обратно
     * в неизменном виде.
     *
     * Если же с каким-то свойством велась работа (значит, оно было инициализировано через getProperty()),
     * то оно будет представлено в виде объекта свойства, и при сохранении оно будет преобразовано в массив.
     *
     * Здесь хранятся только те свойства, которые были подгружены через setDefinition() + те свойства, которые
     * были получены через getProperty(). Сюда не добавляются автоматом те свойства, которые прописаны
     * в getMandatoryPropertySids().
     *
     * Все свойства, как из definition, так и mandatory, необходимо получать через getPropertySids(),
     * который вернет список всех свойств, как mandatory, так и установленных дополнительно.
     *
     */
    protected $_properties = [];

    public function getDefinition()
    {
        // проходим только по свойствам, которые определены (автоматом mandatory не добавляем),
        // чтобы сократить размер выходного массива, который скорее всего будет сохраняться в json.
        $definition = [];
        foreach ($this->_properties as $propertySid => $propertyData) {
            if (is_object($propertyData)) {
                // объект свойства

                // является ли оно внешним свойством, которое не требует сохранения в definition
                if ($propertyData->isExternal) {
                    continue;
                }

                // узнаем, пустое ли оно. Делаем это через обращение к свойству,
                // так как не всегда непустые $propertyData->attributes означает, что
                // свойство не пустое - классу свойства виднее.
                if ($propertyData->isEmpty) {
                    continue;
                }

                // теперь приведем к массиву
                $propertyData = $propertyData->attributes;

                // если он пустой - это ошибка
                if (!$propertyData) {
                    throw new \Exception("Property class considers data is not empty, but attributes are empty");
                }
            }

            // сохраняем, если массив не пустой
            if ($propertyData) {
                $definition[$propertySid] = $propertyData;
            }
        }

        return $definition;
    }


    /**
     * Свойства: массив массивов: ['propertyName' => ['key' => 'value', ...], ...]
     * @param array[] $value
     */
    public function setDefinition($value)
    {
        $this->_properties = $value ?: [];
    }

    public function fields()
    {
        return [];
    }

    public function extraFields()
    {
        return [];
    }

    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        return $this->getDefinition();
    }

    /**
     * @param $sid
     * @param $definition
     * @return GenericProperty
     */
    protected function buildProperty($sid, $definition)
    {
        $class = $this->getPropertyClass($sid);

        /** @var GenericProperty $property */
        $property = new $class($definition);
        $property->sid = $sid;
        $property->formName = "Properties[$sid]";
        $property->owner = $this->owner;

        return $property;
    }


    /**
     * @param $sid
     * @return GenericProperty
     */
    public function getProperty($sid)
    {
        if (!array_key_exists($sid, $this->_properties)) {
            $this->_properties[$sid] = [];
        }

        if (is_array($this->_properties[$sid])) {
            $this->_properties[$sid] = $this->buildProperty($sid, $this->_properties[$sid]);
        }

        return $this->_properties[$sid];
    }

    /**
     * Возвращает класс модели по названию свойства.
     *
     * Этот метод не сделан абстрактным, чтобы класс не был абстрактным, и чтобы можно
     * было работать со свойствами товара (на плоском уровне, без создания моделей).
     * @param $name
     * @throws \Exception
     */
    protected function getPropertyClass($name)
    {
        throw new \Exception("Not implemented");
    }

    protected function getMandatoryPropertySids()
    {
        return [];
    }

    public function getPropertySids()
    {
        $defined = array_keys($this->_properties);
        $mandatory = $this->getMandatoryPropertySids();
        return array_unique(array_merge($defined, $mandatory));
    }

    /**
     * @deprecated Этот метод не используем, так как он должен проинициализировать все свойства,
     * а это может быть дорого. Нужно использовать getPropertySids() для получения списка названий
     * свойств, и потом получать нужные свойства через getProperty($sid)
     */
    public function getProperties()
    {
        throw new \Exception('deprecated');
    }

    public function hasProperties()
    {
        return (bool) $this->getPropertySids();
    }

    // так как свойство может заполняться из пользовательских данных,
    // по умолчанию $safeOnly = true, как в Yii
    public function setProperty($propertySid, $value, $safeOnly = true)
    {
        $property = $this->getProperty($propertySid);
        if (!is_array($value)) {
            $value = ['value' => $value];
        }

        $property->setAttributes($value, $safeOnly);
    }

    protected function getIsPropertyInitialized($propertySid)
    {
        return array_key_exists($propertySid, $this->_properties) && is_object($this->_properties[$propertySid]);
    }

    // инициализированное свойство - это свойство, существующее в _properties и представленное
    // в виде объекта
    protected function getInitializedProperties()
    {
        $initialized = [];
        foreach ($this->_properties as $propertySid => $propertyData) {
            if (is_object($propertyData)) {
                $initialized[$propertySid] = $propertyData;
            }
        }
        return $initialized;
    }

    public function ensureSave()
    {
        foreach ($this->getInitializedProperties() as $property) {
            $property->ensureSave();
        }
    }

    public function getErrors()
    {
        $errors = [];
        foreach ($this->getInitializedProperties() as $property) {
            if ($propertyErrors = $property->errors) {
                $errors[$property->sid] = $propertyErrors;
            }
        }

        return $errors;
    }


    public function save()
    {
        $ok = true;

        foreach ($this->getInitializedProperties() as $property) {
            if (!$property->save()) {
                $ok = false;
            }
        }

        return $ok;
    }

    private $_loadScope;
    public function setLoadScope($value)
    {
        $this->_loadScope = $value;
    }

    /**
     * @return string
     */
    public function getLoadScope()
    {
        return $this->_loadScope;
    }

    public function load($data, $scope = null)
    {
        $this->setLoadScope($scope);
        if ($scope) {
            // для полноценной работы тут требуется поддержка $scope
            // вида YourModel[properties], т. е. с массивом, а не с одним элементом.
            // Пока не реализовано и поддерживается только вариант с простым именем
            // переменной в виде строки.
            $data = @$data[$scope];
        }


        if (!$data) {
            return false;
        }

        foreach ($data ?: [] as $propertySid => $data) {
            $property = $this->getProperty($propertySid);
            if ($scope) {
                $property->setLoadScope("{$scope}[{$propertySid}]");
            } else {
                throw new \Exception("Not implemented");
            }
            $property->load($data, '');
        }

        return true;
    }

    /**
     * @return array Какие оригинальные свойтсва Library заменяет имеющийся набор свойств
     */
    public function getReplaces()
    {
        return [];
    }

    public function isReplaces($fieldName)
    {
        return in_array($fieldName, $this->getReplaces());
    }
}
