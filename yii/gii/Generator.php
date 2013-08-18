<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\gii;

use Yii;
use ReflectionClass;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\View;


/**
 * This is the base class for all generator classes.
 *
 * A generator instance is responsible for taking user inputs, validating them,
 * and using them to generate the corresponding code based on a set of code template files.
 *
 * A generator class typically needs to implement the following methods:
 *
 * - [[getName()]]: returns the name of the generator
 * - [[getDescription()]]: returns the detailed description of the generator
 * - [[generate()]]: generates the code based on the current user input and the specified code template files.
 *   This is the place where main code generation code resides.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Generator extends Model
{
	/**
	 * @var array a list of available code templates. The array keys are the template names,
	 * and the array values are the corresponding template paths or path aliases.
	 */
	public $templates = array();
	/**
	 * @var string the name of the code template that the user has selected.
	 * The value of this property is internally managed by this class.
	 */
	public $template;

	/**
	 * @return string name of the code generator
	 */
	abstract public function getName();
	/**
	 * Generates the code based on the current user input and the specified code template files.
	 * This is the main method that child classes should implement.
	 * Please refer to [[\yii\gii\generators\controller\Generator::generate()]] as an example
	 * on how to implement this method.
	 * @return CodeFile[] a list of code files to be created.
	 */
	abstract public function generate();

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		if (!isset($this->templates['default'])) {
			$this->templates['default'] = $this->defaultTemplate();
		}
		foreach ($this->templates as $i => $template) {
			$this->templates[$i] = Yii::getAlias($template);
		}
	}

	/**
	 * Returns a list of code template files that are required.
	 * Derived classes usually should override this method if they require the existence of
	 * certain template files.
	 * @return array list of code template files that are required. They should be file paths
	 * relative to [[templatePath]].
	 */
	public function requiredTemplates()
	{
		return array();
	}

	/**
	 * Returns the list of sticky attributes.
	 * A sticky attribute will remember its value and will initialize the attribute with this value
	 * when the generator is restarted.
	 * @return array list of sticky attributes
	 */
	public function stickyAttributes()
	{
		return array('template');
	}

	/**
	 * Returns the list of hint messages.
	 * The array keys are the attribute names, and the array values are the corresponding hint messages.
	 * Hint messages will be displayed to end users when they are filling the form for the generator.
	 * @return array the list of hint messages
	 */
	public function hints()
	{
		return array();
	}

	/**
	 * Returns the message to be displayed when the newly generated code is saved successfully.
	 * Child classes may override this method to customize the message.
	 * @return string the message to be displayed when the newly generated code is saved successfully.
	 */
	public function successMessage()
	{
		return 'The code has been generated successfully.';
	}

	/**
	 * Returns the view file for the input form of the generator.
	 * The default implementation will return the "form.php" file under the "views" subdirectory of the
	 * directory containing the generator class file.
	 * @return string the view file for the input form of the generator.
	 */
	public function formView()
	{
		$class = new ReflectionClass($this);
		return dirname($class->getFileName()) . '/views/form.php';
	}

	/**
	 * Returns the root path to the default code template files.
	 * The default implementation will return the "templates" subdirectory of the
	 * directory containing the generator class file.
	 * @return string the root path to the default code template files.
	 */
	public function defaultTemplate()
	{
		$class = new ReflectionClass($this);
		return dirname($class->getFileName()) . '/templates';
	}

	/**
	 * @return string the detailed description of the generator.
	 */
	public function getDescription()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 *
	 * Child classes should override this method like the following so that the parent
	 * rules are included:
	 *
	 * ~~~
	 * return array_merge(parent::rules(), array(
	 *     ...rules for the child class...
	 * ));
	 * ~~~
	 */
	public function rules()
	{
		return array(
			array('template', 'required', 'message' => 'A code template must be selected.'),
			array('template', 'validateTemplate'),
		);
	}

	/**
	 * Loads sticky attributes from an internal file and populates them into the generator.
	 * @internal
	 */
	public function loadStickyAttributes()
	{
		$stickyAttributes = $this->stickyAttributes();
		$attributes[] = 'template';
		$path = $this->getStickyDataFile();
		if (is_file($path)) {
			$result = @include($path);
			if (is_array($result)) {
				foreach ($stickyAttributes as $name) {
					if (isset($result[$name])) {
						$this->$name = $result[$name];
					}
				}
			}
		}
	}

	/**
	 * Saves sticky attributes into an internal file.
	 * @internal
	 */
	public function saveStickyAttributes()
	{
		$stickyAttributes = $this->stickyAttributes();
		$stickyAttributes[] = 'template';
		$values = array();
		foreach ($stickyAttributes as $name) {
			$values[$name] = $this->$name;
		}
		$path = $this->getStickyDataFile();
		@mkdir(dirname($path), 0755, true);
		file_put_contents($path, "<?php\nreturn " . var_export($values, true) . ";\n");
	}

	/**
	 * @return string the file path that stores the sticky attribute values.
	 * @internal
	 */
	public function getStickyDataFile()
	{
		return Yii::$app->getRuntimePath() . '/gii-' . Yii::getVersion() . '/' . str_replace('\\', '-',get_class($this)) . '.php';
	}

	/**
	 * Saves the generated code into files.
	 * @param CodeFile[] $files the code files to be saved
	 * @param array $answers
	 * @param string $results this parameter receives a value from this method indicating the log messages
	 * generated while saving the code files.
	 * @return boolean whether there is any error while saving the code files.
	 */
	public function save($files, $answers, &$results)
	{
		$lines = array('Generating code using template "' . $this->getTemplatePath() . '"...');
		$hasError = false;
		foreach ($files as $file) {;
			$relativePath = $file->getRelativePath();
			if (isset($answers[$file->id]) && $file->operation !== CodeFile::OP_SKIP) {
				$error = $file->save();
				if (is_string($error)) {
					$hasError = true;
					$lines[] = "generating $relativePath\n<span class=\"error\">$error</span>";
				} else {
					$lines[] = $file->operation === CodeFile::OP_NEW ? " generated $relativePath" : " overwrote $relativePath";
				}
			} else {
				$lines[] = "   skipped $relativePath";
			}
		}
		$lines[] = "done!\n";
		$results = implode("\n", $lines);

		return $hasError;
	}

	/**
	 * @return string the root path of the template files that are currently being used.
	 * @throws InvalidConfigException if [[template]] is invalid
	 */
	public function getTemplatePath()
	{
		if (isset($this->templates[$this->template])) {
			return $this->templates[$this->template];
		} else {
			throw new InvalidConfigException("Unknown template: {$this->template}");
		}
	}

	/**
	 * Generates code using the specified code template and parameters.
	 * Note that the code template will be used as a PHP file.
	 * @param string $template the code template file
	 * @param array $params list of parameters to be passed to the template file.
	 * @return string the generated code
	 */
	public function render($template, $params = array())
	{
		$view = new View;
		$params['generator'] = $this;
		return $view->renderFile($template, $params, $this);
	}

	/**
	 * Validates the template selection.
	 * This method validates whether the user selects an existing template
	 * and the template contains all required template files as specified in [[requiredTemplates()]].
	 */
	public function validateTemplate()
	{
		$templates = $this->templates;
		if (!isset($templates[$this->template])) {
			$this->addError('template', 'Invalid template selection.');
		} else {
			$templatePath = $this->templates[$this->template];
			foreach ($this->requiredTemplates() as $template) {
				if (!is_file($templatePath . '/' . $template)) {
					$this->addError('template', "Unable to find the required code template file '$template'.");
				}
			}
		}
	}

	/**
	 * Validates an attribute to make sure it is not taking a PHP reserved keyword.
	 * @param string $attribute the attribute to be validated
	 * @param array $params validation parameters
	 */
	public function validateReservedWord($attribute, $params)
	{
		static $keywords = array(
			'__class__',
			'__dir__',
			'__file__',
			'__function__',
			'__line__',
			'__method__',
			'__namespace__',
			'__trait__',
			'abstract',
			'and',
			'array',
			'as',
			'break',
			'case',
			'catch',
			'callable',
			'cfunction',
			'class',
			'clone',
			'const',
			'continue',
			'declare',
			'default',
			'die',
			'do',
			'echo',
			'else',
			'elseif',
			'empty',
			'enddeclare',
			'endfor',
			'endforeach',
			'endif',
			'endswitch',
			'endwhile',
			'eval',
			'exception',
			'exit',
			'extends',
			'final',
			'finally',
			'for',
			'foreach',
			'function',
			'global',
			'goto',
			'if',
			'implements',
			'include',
			'include_once',
			'instanceof',
			'insteadof',
			'interface',
			'isset',
			'list',
			'namespace',
			'new',
			'old_function',
			'or',
			'parent',
			'php_user_filter',
			'print',
			'private',
			'protected',
			'public',
			'require',
			'require_once',
			'return',
			'static',
			'switch',
			'this',
			'throw',
			'trait',
			'try',
			'unset',
			'use',
			'var',
			'while',
			'xor',
		);
		$value = $this->$attribute;
		foreach (explode('\\', strtolower($value)) as $name) {
			if (in_array($name, $keywords)) {
				$this->addError($attribute, $this->getAttributeLabel($attribute) . ' cannot take a reserved PHP keyword.');
				return;
			}
		}
	}
}
