<?php

namespace Swoft\Db\Entity;

use Swoft\App;

/**
 * Stub操作类 
 *
 * @uses      SetGetGenerator
 * @version   2017年11月7日
 * @author    caiwh <471113744@qq.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */

class SetGetGenerator
{
    /**
     * @var Schema $schema schema对象
     */
    private $schema;

    /**
     * @var string $folder 模板目录
     */
    public $folder = 'Stub';
    
    /**
     * @var string $modelStub ModelStub
     */
    private $modelStubFile = 'Model.stub';

    /**
     * @var string $PropertyStubFile PropertyStub 
     */
    private $propertyStubFile = 'Property.stub';

    /**
     * @var string $setterStub SettrStub
     */
    private $setterStubFile = 'Setter.stub';

    /**
     * @var string $getterStub GetterStub
     */
    private $getterStubFile = 'Getter.stub';

    /**
     * @var string $propertyStub 需要替换property的内容
     */
    private $propertyStub = '';

    /**
     * @var string $setterStub 需要替换setter的内容
     */
    private $setterStub = '';

    /**
     * @var string $getterStub 需要替换的getter的内容
     */
    private $getterStub = '';

    public function __construct()
    {
        $this->folder = __DIR__ . '/' . $this->folder . '/';
    }

    /**
     * @__invoke
     * @override
     *
     * @param Schema $schema      schema对象
     * @param array  $uses        需要use的类
     * @param string $entity      实体
     * @param mixed  $entityName  实体中文名
     * @param string $entityClass 实体类
     * @param string $entityDate  实体生成日期
     * @param array  $fields      字段
     */
    public function __invoke(
        Schema $schema,
        array $uses,
        string $extends,
        string $entity,
        $entityName,
        string $entityClass,
        string $entityDate,
        array $fields)
    {
        $this->schema = $schema;
        $entityStub = $this->generateModel();
        $usesContent = '';
        foreach ($uses as $useClass) {
            $usesContent .= "use {$useClass};" . PHP_EOL;
        }

        $this->parseFields($fields);

        $entityFile = str_replace([
            '{{uses}}',
            '{{extends}}',
            '{{entity}}',
            '{{entityName}}',
            '{{entityClass}}',
            '{{entityDate}}',
            '{{property}}',
            '{{setter}}',
            '{{getter}}'
        ], [
            $usesContent,
            $extends,
            $entity,
            $entityName,
            $entityClass,
            $entityDate,
            $this->propertyStub,
            $this->setterStub,
            $this->getterStub
        ], $entityStub);

        file_put_contents(App::getAlias('@entityPath')."/{$entityClass}.php", $entityFile);
    }

    /**
     * 开始解析字段信息
     *
     * @param array $fields 字段
     */
    private function parseFields(array $fields)
    {
        $propertyStub = $this->generateProperty();
        $setterStub = $this->generateSetter();
        $getterStub = $this->generateGetter();
        foreach ($fields as $fieldInfo) {
            $this->parseProperty($propertyStub, $fieldInfo);
            $this->parseSetter($setterStub, $fieldInfo);
            $this->parseGetter($getterStub, $fieldInfo);
        }
    }

    /**
     * 解析Property
     *
     * @param string $propertyStub 属性模板
     * @param array  $fieldInfo    字段信息
     *
     */
    private function parseProperty(string $propertyStub, array $fieldInfo)
    {
        $property = $fieldInfo['name'];
        $primaryKey = $fieldInfo['key'] === 'PRI' ? true : false;
        $required = $primaryKey ? false : ($fieldInfo['nullable'] === 'NO' ? true : false);
        $default = !empty($fieldInfo['default']) ? $fieldInfo['default'] : false;
        $dbType = isset($this->schema->dbSchema[$fieldInfo['type']]) ? $this->schema->dbSchema[$fieldInfo['type']] : '' ;
        $phpType = isset($this->schema->phpSchema[$fieldInfo['type']]) ? $this->schema->phpSchema[$fieldInfo['type']] : 'mixed' ;
        $length = $fieldInfo['length'];
        $columnType = $fieldInfo['column_type'];
        $comment = $fieldInfo['column_comment'];
        $isEnum = strpos($columnType, 'enum') === false ? false : true;
        if ($isEnum) {
           preg_match_all("/enum\((.*?)\)/", $columnType, $matches); 
           $enumParam = $matches[1][0];
           $enumParam = explode(',', str_replace('\'', '', $enumParam));
        }

        $formatComment = "     * @var {$phpType} \${$property} {$comment}\n";
        if (!empty($comment)) {
            $formatComment = "     * @var {$phpType} \${$property}\n";
        }

        $this->propertyStub .= PHP_EOL . str_replace([
            "{{comment}}\n",
            "{{@Id}}\n",
            '{{property}}',
            '{{type}}',
            '{{length}}',
            "{{@Required}}\n",
            '{{hasDefault}}'
        ], [
            $formatComment,
            $primaryKey ? "     * @Id()\n" : '',
            $property,
            !empty($dbType) ? $dbType :($isEnum ? '"feature-enum"' : (is_int($default) ? '"int"' : '"string"')),
            $length !== null ? ", length={$length}" : '',
            $required ? "     * @Required()\n" : '',
            $default !== false ? (is_int($default) ? " = {$default};" : " = '{$default}';") : ($required ? ' = \'\';' : ';')
        ], $propertyStub);
    }

    /**
     * 解析Setter
     *
     * @param string $setterStub setter模板
     * @param array  $fieldInfo   字段信息
     *
     */
    private function parseSetter(string $setterStub, array $fieldInfo)
    {
        $function = 'set' . ucfirst($fieldInfo['name']);
        $primaryKey = $fieldInfo['key'] === 'PRI' ? true : false;
        $attribute = $fieldInfo['name'];
        $type = isset($this->schema->phpSchema[$fieldInfo['type']]) ? $this->schema->phpSchema[$fieldInfo['type']] : 'mixed' ;
        $this->setterStub .= PHP_EOL . str_replace([
            '{{function}}',
            '{{attribute}}',
            '{{type}}',
            '{{hasReturnType}}'
        ], [
            $function,
            $attribute,
            $type !== 'mixed' ? "{$type} " : '',
            $primaryKey ? '' : ': self'
        ], $setterStub);
    }

    /**
     * 解析Getter
     *
     * @param string $getterStub getter模板
     * @param array  $fieldInfo   字段信息
     *
     */
    private function parseGetter(string $getterStub, array $fieldInfo)
    {
        $function = 'get' . ucfirst($fieldInfo['name']);
        $attribute = $fieldInfo['name'];
        $default = !empty($fieldInfo['default']) ? $fieldInfo['default'] : false;
        $primaryKey = $fieldInfo['key'] === 'PRI' ? true : false;
        $returnType = isset($this->schema->phpSchema[$fieldInfo['type']]) ? $this->schema->phpSchema[$fieldInfo['type']] : 'mixed' ;
        $this->getterStub .= PHP_EOL . str_replace([
            '{{function}}',
            '{{attribute}}',
            '{{coReturnType}}',
            '{{returnType}}',
        ], [
            $function,
            $attribute,
            $returnType,
            $returnType !== 'mixed' && !$primaryKey  && $default !== false ? ": {$returnType}" : '',
        ], $getterStub);
    }

    /**
     * 创建Model模板
     *
     * return string
     */
    private function generateModel(): string
    {
        return file_get_contents($this->folder . $this->modelStubFile);
    }

    /**
     * 创建Setter模版
     *
     * return string
     */
    private function generateSetter(): string
    {
        return file_get_contents($this->folder . $this->setterStubFile);
    }

    /**
     * 创建Getter模板
     *
     * @return string
     */
    private function generateGetter(): string
    {
        return file_get_contents($this->folder . $this->getterStubFile);
    }

    /**
     * 创建Property模板
     *
     * @return string
     */
    private function generateProperty(): string
    {
        return file_get_contents($this->folder . $this->propertyStubFile);
    }
}
