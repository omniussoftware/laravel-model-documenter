<?php


namespace Enz0project\ModelDocumenter\Modules;


use Enz0project\ModelDocumenter\ModelData;

interface ModifiesModelData {
	public function modelData(): ModelData;
}