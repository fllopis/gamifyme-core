<?php
namespace Funks;

class Documents
{
	var $app;

	public function __construct($app)
	{
        $this->app = $app;
  	}

    //Function to get the document by shortcode and lang
    public function getDocumentByShortcodeAndLang($shortcode , $slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        $datos = $this->app['bd']->fetchObject("
            SELECT dl.url
            FROM documents d, documents_lang dl
            WHERE d.id = dl.id_document
            AND dl.id_lang = '".$id_lang."'
            AND d.shortcode = '".$shortcode."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

	//Function to get documents filtered
    public function getDocumentsFiltered($comienzo, $limite, $applyLimit=true,){

        //Obtenemos idioma default.
        $lang = $this->app['lang']->getDefaultLanguage();

        if($applyLimit)
            $limit = "LIMIT $comienzo, $limite";
        else
            $limit = "";

        $documents = $this->app['bd']->fetchObject("
            SELECT d.*
            FROM documents d
            ORDER BY d.id DESC
            $limit
        ");

        if (count($documents) === 0) {
            return [];
        }

        $languages = $this->app['lang']->getLanguagesVisibles();

        // Get all language_documents of those documents in all visible languages
        $documentIds = array_column($documents, 'id');
        $documentIdList = implode(',', array_map('intval', $documentIds));
        $languageIds = array_column($languages, 'id');
        $languageIdList = implode(',', array_map('intval', $languageIds));

        $docsLang = $this->app['bd']->fetchObject("
            SELECT *
            FROM documents_lang
            WHERE id_document IN ($documentIdList)
            AND id_lang IN ($languageIdList)
        ");

        // Reorganize docs_lang by id_document and language
        $langMap = [];
        foreach ($docsLang as $dl) {
            $langMap[$dl->id_document][$dl->id_lang] = $dl;
        }

        // Prepare documents with your languages
        foreach ($documents as $doc) {
            $doc->name = '';
            $doc->url = '';
            $doc->langs = [];

            foreach ($languages as $language) {

                $doc->langs[$language->slug] = new \stdClass();
                
                if (isset($langMap[$doc->id][$language->id])) {
                    $doc->langs[$language->slug]->status = true;

                    $doc->langs[$language->slug]->name  = $langMap[$doc->id][$language->id]->name;
                    $doc->langs[$language->slug]->url   = $langMap[$doc->id][$language->id]->url;
                } else {
                    $doc->langs[$language->slug]->status = false;
                }
            }
        }

        return $documents;
    }

    //Function to get document base by id
    public function getDocumentBaseById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM documents
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function that get the document by id and language
    public function getDocumentById($id_document, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->app['lang']->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT dl.*
            FROM documents d, documents_lang dl
            WHERE d.id = dl.id_document 
            AND dl.id_lang = '".$id_lang."'
            AND dl.id_document  = '".$id_document."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get document with translations
    public function getDocumentByIdWithTranslations($id){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = $this->getDocumentBaseById($id);

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $document_translations = $this->getDocumentById($id, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($document_translations)
                $data->{'lang_'.$lang->slug} = $document_translations;
        }

        return $data;
    }

    //Function to delete all page data
    public function deleteDocumentById($id_document){

        //Getting the document in languages to delete any document upload
        $documents_langs = $this->app['bd']->fetchObject("
            SELECT dl.*
            FROM documents d, documents_lang dl
            WHERE d.id = dl.id_document 
            AND dl.id_document  = '".$id_document."'
        ");

        if(count($documents_langs) > 0){
            foreach($documents_langs as $key => $document_lang){

                //Deleting the document for each language
                if(isset($document_lang->url) && $document_lang->url != ""){
                    if(file_exists(_PATH_.$document_lang->url))
                        unlink(_PATH_.$document_lang->url);
                }
            }
        }

        //Deleting full path
        $fullRoute = _PATH_ . 'documents/'.$id_document.'/';
        if(file_exists($fullRoute) && is_dir($fullRoute)){
			rmdir($fullRoute);
        }

        //Deleting the file of document in languages
        $this->app['bd']->query("DELETE FROM documents_lang WHERE id_document = '".$id_document."'");

        //Deleting document base
        $this->app['bd']->query("DELETE FROM documents WHERE id = '".$id_document."'");
    }

    //Checking if shortcode is unique
    public function isUniqueShortcode($id, $shortcode){
        $documents = $this->app['bd']->countRows("SELECT id FROM documents WHERE id != '".$id."' AND shortcode = '".$shortcode."'");
        return ($documents == 0) ? true : false;
    }
}
