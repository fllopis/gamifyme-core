<?php
namespace Funks;

class About
{
	var $app;

	public function __construct($app)
	{
        $this->app = $app;
  	}

    //Function to return all data about me for language
    public function getAboutMeByLang($slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        //Data to return
        $about	= new \stdClass();

        //About BASE:: Image, title and description
        $aboutBase = $this->app['bd']->fetchObject("
            SELECT a.image as image, al.title as title, al.description as description
            FROM about a, about_lang al
            WHERE a.id = al.id_about
            AND al.id_lang = '".$id_lang."'
        ");

        if(count($aboutBase) == 1){
            $about->image = $aboutBase[0]->image;
            $about->title = $aboutBase[0]->title;
            $about->description = $aboutBase[0]->description;
        }

        //Getting about social links
        $about->social_links = $this->getAboutSocialLinksVisible();

        //Getting about blocks
        $about->blocks = $this->getAboutBlocksByLang($id_lang);

        return $about;
    }

	public function getAboutBaseById($id){

		$datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM about
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
	}

    //Function that get the about me by id and language
    public function getAboutById($id_about, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM about_lang
            WHERE id_lang = '".$id_lang."'
            AND id_about  = '".$id_about."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get about me with translations
    public function getAboutByIdWithTranslations($id){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = $this->getAboutBaseById($id);

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $about_translations = $this->getAboutById($id, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($about_translations)
                $data->{'lang_'.$lang->slug} = $about_translations;
        }

        return $data;
    }

    /********************************************
	 *											*
	 * 			FUNCTIONS ABOUT BLOCKS 		    *
	 *											*
     ********************************************/

    //Function to get about blocks in base to language
    public function getAboutBlocksByLang($id_lang){
        return $this->app['bd']->fetchObject("
            SELECT abl.title as title, abl.content as content
            FROM about_blocks ab, about_blocks_lang abl
            WHERE ab.id = abl.id_about_block
            AND abl.id_lang = '".$id_lang."'
            ORDER BY ab.id ASC
        ");
    }

    //Function to get about blocks filtered
    public function getAboutBlocksFiltered($comienzo, $limite, $applyLimit=true){

        //Obtenemos idioma default.
        $lang = $this->app['lang']->getDefaultLanguage();

        if($applyLimit)
            $limit = "LIMIT $comienzo, $limite";
        else
            $limit = "";

        $aboutBlocks = $this->app['bd']->fetchObject("
            SELECT abl.*
            FROM about_blocks ab, about_blocks_lang abl
            WHERE ab.id = abl.id_about_block 
            AND abl.id_lang = '".$lang->id."'
            ORDER BY ab.id ASC
            $limit
        ");

        //Mapping to detect his different languages.
        if(count($aboutBlocks) > 0){

            $languages = $this->app['lang']->getLanguagesVisibles();

            foreach($aboutBlocks as $key => $aboutBlock){

                $aboutBlock->title 	    = '';
                $aboutBlock->content 	= '';
                $aboutBlock->langs 	    = [];

                foreach($languages as $key => $language){

                    $langData = $this->app['bd']->fetchObject("SELECT * FROM about_blocks_lang WHERE id_about_block  = '".$aboutBlock->id_about_block."' AND id_lang = '".$language->id."'");

                    if(count($langData) == '1'){

                        //If we have data in the language, we get it.
                        $langData = $langData[0];
                        $aboutBlock->langs[$language->slug] = true;

                        //Store the data in the object.
                        if(isset($language->is_default) && $language->is_default == 1){
                            $aboutBlock->title = $langData->title;
                            $aboutBlock->content = $langData->content;
                        }
                    }
                    else
                        $aboutBlock->langs[$language->slug] = false;
                }
            }
        }

        return $aboutBlocks;
    }

    //Function to get about blocks base by id
    public function getAboutBlockBaseById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM about_blocks
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function that get the about block by id and language
    public function getAboutBlockById($id_about_block, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM about_blocks_lang
            WHERE id_lang = '".$id_lang."'
            AND id_about_block  = '".$id_about_block."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get about block with translations
    public function getAboutBlockByIdWithTranslations($id){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = $this->getAboutBlockBaseById($id);

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $about_translations = $this->getAboutBlockById($id, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($about_translations)
                $data->{'lang_'.$lang->slug} = $about_translations;
        }

        return $data;
    }

    //Function to delete about block
    public function deleteAboutBlock($id){

    	//Getting about block data
    	$about_base = $this->getAboutBlockBaseById($id);

        //Delete the about block data and languages
        $this->app['bd']->query("DELETE FROM about_blocks WHERE id = '".$id."'");
        $this->app['bd']->query("DELETE FROM about_blocks_lang WHERE id_about_block = '".$id."'");
    }

    /************************************************
	 *											    *
	 * 			FUNCTIONS ABOUT SOCIAL LINKS 		*
	 *											    *
     ************************************************/

    //Function to get social links visible
    public function getAboutSocialLinksVisible(){
        return $this->app['bd']->fetchObject("
            SELECT name, url, icon, external
            FROM about_social_links
            WHERE visible = '1'
            ORDER BY position ASC
        ");
    }

    //Function to get about blocks filtered
    public function getAboutSocialLinksFiltered($comienzo = 0, $limite = 1000, $applyLimit=true){

        if($applyLimit)
            $limit = "LIMIT $comienzo, $limite";
        else
            $limit = "";

        return $this->app['bd']->fetchObject("
            SELECT *
            FROM about_social_links
            ORDER BY position ASC
            $limit
        ");
    }

    //Function to get about social base by id
    public function getAboutSocialLinkById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM about_social_links
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to delete about social link
    public function deleteAboutSocialLink($id){
        $this->app['bd']->query("DELETE FROM about_social_links WHERE id = '".$id."'");
    }
}
