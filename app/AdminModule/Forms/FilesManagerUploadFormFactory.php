<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\FileStorage\Files\HashFile;
use App\FileStorage\Files\HashImageEntity;
use App\Forms\BaseFormFactory;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

class FilesManagerUploadFormFactory extends BaseFormFactory
{
	public function __construct(private Model\Database\Files $filesModel)
	{
		parent::__construct();
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		 /*$form->addMultiUpload('files', 'Files');;
		 /* ->setRequired(false)
		  ->addRule(Form::IMAGE);
		 */
		$form->addFileUpload('files');
		/* ->setRequired(true)
		  ->addRule(Form::IMAGE); */

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		//defaults
		if ($this->isEditMode()) {
			//$form->setDefaults($values);
		}

		return $form;
	}


	public function formSucceeded(Form $form, ArrayHash $values): void
	{
		if ($values->files) {
			\Tracy\Debugger::timer("p");
			/** @var HashFile $file */
			foreach ($values->files as $file) {
				/*if($file->isOk()) {
					$file = $this->multiFileUploadModel->save($file, array());
				}*/

				$this->filesModel->insert(ArrayHash::from(array(
					"originalName" => $file->getName(),
					"fileFolderId" => $form->getPresenter()->id,
					"diskName" => $file->getHash(),
					"extension" => $file->getExtension(),
					"mimeType" => $file->getMimeType(),
					"size" => $file->getSize(),
					"isImage" => $file instanceof HashImageEntity,
				)));
			}
			\Tracy\Debugger::log("cas:".\Tracy\Debugger::timer("p"));
		}
		$form->getPresenter()->redirect('this');
	}

}
