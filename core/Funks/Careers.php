<?php
namespace Funks;

class Careers
{
	var $app;

	public function __construct($app)
	{
        $this->app = $app;
  	}

    //Function to get number of careers by type
    public function getCarrersStats($type){
        return $this->app['bd']->countRows("
            SELECT id
            FROM career
            WHERE type = '".$type."'
        ");
    }

    //Function to get the last careers
    public function getLastCareers($type, $limit = 5, $slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        return $this->app['bd']->fetchObject("
            SELECT c.*, cl.title as title, cl.description as description
            FROM career c, career_lang cl
            WHERE c.id = cl.id_career
            AND cl.id_lang = '".$id_lang."'
            AND c.type = '".$type."'
            ORDER BY c.id DESC
            LIMIT $limit
        ");
    }

    //Function to get the different careers
    public function getCareersByLang($type, $slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        return $this->app['bd']->fetchObject("
            SELECT c.*, cl.title as title, cl.description as description
            FROM career c, career_lang cl
            WHERE c.id = cl.id_career
            AND cl.id_lang = '".$id_lang."'
            AND c.type = '".$type."'
            ORDER BY c.date_from_year DESC, c.id DESC
        ");
    }

	//Function to get careers filtered
    public function getCareersFiltered($comienzo, $limite, $applyLimit=true, $type){

        //Obtenemos idioma default.
        $lang = $this->app['lang']->getDefaultLanguage();

        if($applyLimit)
            $limit = "LIMIT $comienzo, $limite";
        else
            $limit = "";

        $careers = $this->app['bd']->fetchObject("
            SELECT c.*
            FROM career c, career_lang cl
            WHERE c.id = cl.id_career
            AND cl.id_lang = '".$lang->id."'
            AND c.type = '".$type."'
            ORDER BY c.date_from_year DESC, c.id DESC
            $limit
        ");

        //Mapping to detect his different languages.
        if(count($careers) > 0){

            $languages = $this->app['lang']->getLanguagesVisibles();

            foreach($careers as $key => $career){

                $career->title 	        = '';
                $career->description 	= '';
                $career->langs          = [];

                foreach($languages as $key => $language){

                    $langData = $this->app['bd']->fetchObject("SELECT * FROM career_lang WHERE id_career  = '".$career->id."' AND id_lang = '".$language->id."'");

                    if(count($langData) == '1'){

                        //If we have data in the language, we get it.
                        $langData = $langData[0];
                        $career->langs[$language->slug] = true;

                        //Store the data in the object.
                        if(isset($language->is_default) && $language->is_default == 1){
                            $career->title = $langData->title;
                            $career->description = $langData->description;
                        }
                    }
                    else
                        $career->langs[$language->slug] = false;
                }
            }
        }

        return $careers;
    }

    //Function to get career base by id
    public function getCareerBaseById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM career
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function that get the career by id and language
    public function getCareerById($id_career, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->app['lang']->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT cl.*
            FROM career c, career_lang cl
            WHERE c.id = cl.id_career 
            AND cl.id_lang = '".$id_lang."'
            AND cl.id_career  = '".$id_career."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get career with translations
    public function getCarrerByIdWithTranslations($id){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = $this->getCareerBaseById($id);

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $career_translations = $this->getCareerById($id, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($career_translations)
                $data->{'lang_'.$lang->slug} = $career_translations;
        }

        return $data;
    }

    //Function to delete all career data
    public function deleteCareerById($id_career){

        //Getting career base info
        $career = $this->getCareerBaseById($id_career);

        //Deleting translations for this career
        $this->app['bd']->query("DELETE FROM career_lang WHERE id_career = '".$id_career."'");

        //Now deleting the logo for this career
        if(isset($career->logo) && $career->logo != ""){
            if(file_exists(_PATH_.$career->logo))
                unlink(_PATH_.$career->logo);
        }

        //Finally deleting the career from main table
        $this->app['bd']->query("DELETE FROM career WHERE id = '".$id_career."'");
    }

    //Function to get the correct date format to frontend
    public function getCorrectFormatForDateFromTo($career, $translation){
        $dateFromShow = "";
        $dateToShow = "";
        $dateFromToComplete = "";

        //Checking date FROM
        if(isset($career->date_from_month) && $career->date_from_month != ""){
            $dateFromShow .= $career->date_from_month;
        }
        if(isset($career->date_from_year) && $career->date_from_year != ""){
            //Adding dash if has month configure
            $dateFromShow .= ($dateFromShow != "") ? "/" : "";
            $dateFromShow .= $career->date_from_year;
        }

        //Checking date TO
        if(isset($career->currently) && $career->currently == '0'){
            if(isset($career->date_to_month) && $career->date_to_month != ""){
                $dateToShow .= $career->date_to_month;
            }
            if(isset($career->date_to_year) && $career->date_to_year != ""){
                //Adding dash if has month configure
                $dateToShow .= ($dateToShow != "") ? "/" : "";
                $dateToShow .= $career->date_to_year;
            }
        } else {
            $dateToShow .= $this->app['lang']->getTranslationByShortcode('career-currently', $translation);
        }

        //Concat both
        $dateFromToComplete = $dateFromShow;
        $dateFromToComplete .= ($dateFromToComplete != "" && $dateToShow != "") ? " - " . $dateToShow : $dateToShow;

        return $dateFromToComplete;
    }
}
