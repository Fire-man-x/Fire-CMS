<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\AdminModule\Presenters\FilesManagerPresenter;
use App\FileStorage\Files\HashFile;
use App\FileStorage\Files\HashImageEntity;
use App\Model;
use Nette\Utils\ArrayHash;
use Contributte\FileUpload\FileUploadControl;

class FilesManagerUploadFormFactory extends AdminFormFactory
{
	public function __construct(private readonly Model\Database\Files $filesModel)
	{
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		 /*$form->addMultiUpload('files', 'Files');;
		 /* ->setRequired(false)
		  ->addRule(Form::IMAGE);
		 */
		// upload zůstává přímo na formuláři, ne v `data`: knihovna jquery-fileupload hledá soubor i token podle
		// plochého HTML jména (`$files[$control->getHtmlName()]`, POST `<jméno>-token`), `data[files]` by nenašla.
		// Z hodnot je vynechaný (AdminFormValues vlastnost `files` nemá), hodnota se čte přímo z prvku.
		$form->addFileUpload('files')
			->setOmitted();
		/* ->setRequired(true)
		  ->addRule(Form::IMAGE); */

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		//defaults
		if ($this->isEditMode()) {
			//$form->setDefaults($values);
		}

		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		$filesControl = $form['files'];
		$files = $filesControl instanceof FileUploadControl ? $filesControl->getValue() : null;
		$presenter = $form->getPresenter();

		if (is_array($files) && $presenter instanceof FilesManagerPresenter) {
			\Tracy\Debugger::timer("p");
			/** @var HashFile $file */
			foreach ($files as $file) {
				/*if($file->isOk()) {
					$file = $this->multiFileUploadModel->save($file, array());
				}*/

				$this->filesModel->insert(ArrayHash::from(array(
					"originalName" => $file->getName(),
					"fileFolderId" => $presenter->id,
					"diskName" => $file->getHash(),
					"extension" => $file->getExtension(),
					"mimeType" => $file->getMimeType(),
					"size" => $file->getSize(),
					"isImage" => $file instanceof HashImageEntity,
				)));
			}
			\Tracy\Debugger::log("cas:".\Tracy\Debugger::timer("p"));
		}
		$presenter->redirect('this');
	}

}
