<?php
require_once("Services/Form/classes/class.ilPropertyFormGUI.php");
require_once("Services/Form/classes/class.ilSelectInputGUI.php");
require_once("Services/Form/classes/class.ilHiddenInputGUI.php");
require_once("Services/ReportsRepository/classes/class.catFilterGUI.php");

class catFilterSingleselectGUI extends catFilterGUI {
	protected $filter;
	protected $path;

	public function __construct($filter, $path) {
		$this->filter = $filter;
		$this->path = $path;
	}

	public function path() {
		return $this->path;
	}

	public function fillForm(ilPropertyFormGUI $form) {
		$select = new ilSelectInputGUI($this->filter->label(), "filter[$this->path]");
		$select->setInfo($this->filter->description());
		$select->setOptions($this->filter->options());
		$select->setValue($this->filter->default_choice());
		$form->addItem($select);

		return $form;
	}
}