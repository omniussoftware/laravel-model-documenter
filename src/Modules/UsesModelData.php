<?php


namespace Enz0project\ModelDocumenter\Modules;


use Enz0project\ModelDocumenter\ModelData;

interface UsesModelData {
	public function setModelData(ModelData $modelData);
}