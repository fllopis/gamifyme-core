<?php
namespace Funks;

class Pages
{
	var $app;

	public function __construct($app)
	{
        $this->app = $app;
  	}

    //Function to get the page by zone and lang
    public function getPagesForZoneAndLang($zone, $slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        return $this->app['bd']->fetchObject("
            SELECT p.title as internal_title, p.mod_id as mod_id, pl.*
            FROM pages p, pages_lang pl
            WHERE p.id = pl.id_page
            AND pl.id_lang = '".$id_lang."'
            AND p.zone = '".$zone."'
            ORDER BY p.id ASC
        ");
    }

    //Function to get the page by slug and lang
    public function getPageBySlugAndByLang($slug, $slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        $data = $this->app['bd']->fetchObject("
            SELECT p.mod_id as mod_id, pl.*
            FROM pages p, pages_lang pl
            WHERE p.id = pl.id_page
            AND pl.id_lang = '".$id_lang."'
            AND pl.slug = '".$slug."'
        ");

        if(count($data) == '1')
            return $data[0];
        else
            return false;
    }

	//Function to get pages filtered
    public function getPagesFiltered($comienzo, $limite, $applyLimit=true,){

        //Obtenemos idioma default.
        $lang = $this->app['lang']->getDefaultLanguage();

        if($applyLimit)
            $limit = "LIMIT $comienzo, $limite";
        else
            $limit = "";

        $pages = $this->app['bd']->fetchObject("
            SELECT p.*
            FROM pages p, pages_lang pl
            WHERE p.id = pl.id_page
            AND pl.id_lang = '".$lang->id."'
            ORDER BY p.id DESC
            $limit
        ");

        //Mapping to detect his different languages.
        if(count($pages) > 0){

            $languages = $this->app['lang']->getLanguagesVisibles();

            foreach($pages as $key => $page){

                $page->h1 	            = '';
                $page->slug             = '';
                $page->seo_title 	    = '';
                $page->seo_description 	= '';
                $page->langs          = [];

                foreach($languages as $key => $language){

                    $langData = $this->app['bd']->fetchObject("SELECT * FROM pages_lang WHERE id_page  = '".$page->id."' AND id_lang = '".$language->id."'");

                    if(count($langData) == '1'){

                        //If we have data in the language, we get it.
                        $langData = $langData[0];
                        $page->langs[$language->slug] = true;

                        //Store the data in the object.
                        if(isset($language->is_default) && $language->is_default == 1){
                            $page->h1 = $langData->h1;
                            $page->slug = $langData->slug;
                            $page->seo_title = $langData->seo_title;
                            $page->seo_description = $langData->seo_description;
                        }
                    }
                    else
                        $page->langs[$language->slug] = false;
                }
            }
        }

        return $pages;
    }

    //Function to get page base by id
    public function getPageBaseById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM pages
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function that get the page by id and language
    public function getPageById($id_page, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->app['lang']->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT pl.*
            FROM pages p, pages_lang pl
            WHERE p.id = pl.id_page 
            AND pl.id_lang = '".$id_lang."'
            AND pl.id_page  = '".$id_page."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get page with translations
    public function getPageByIdWithTranslations($id){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = $this->getPageBaseById($id);

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $page_translations = $this->getPageById($id, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($page_translations)
                $data->{'lang_'.$lang->slug} = $page_translations;
        }

        return $data;
    }

    //Function to delete all page data
    public function deletePageById($id_page){
        //Deleting complete page
        $this->app['bd']->query("DELETE FROM pages_lang WHERE id_page = '".$id_page."'");
        $this->app['bd']->query("DELETE FROM pages WHERE id = '".$id_page."'");
    }

    public function isAvailableSlug($id, $slug, $id_lang){
        return $this->app['bd']->countRows("SELECT slug FROM pages_lang WHERE id_page != '".$id."' AND slug = '".$slug."' AND id_lang = '".$id_lang."'");
    }
}
