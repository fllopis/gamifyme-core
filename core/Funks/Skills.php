<?php
namespace Funks;

class Skills
{
	var $app;

	public function __construct($app)
	{
        $this->app = $app;
  	}

    //Function to get skills stats
    public function getSkillsStats(){
		return $this->app['bd']->countRows("SELECT id FROM skills");
	}

	//Function to return all data skill information for language
    public function getSkillInformationByLang($slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        //Data to return
        $data	= new \stdClass();

        //Skill information
        $data->info = $this->app['bd']->fetchObject("
            SELECT title, description
            FROM skills_langs
            WHERE id_lang = '".$id_lang."'
        ")[0];

        //Skills
        $data->skills = $this->getSkills();

        return $data;
    }

	//Function that get the skills
	public function getSkills(){
		return $this->app['bd']->fetchObject("SELECT * FROM skills ORDER BY position ASC");
	}

	public function getAll(){
		return $this->app['bd']->fetchObject("SELECT * FROM skills ORDER BY position ASC");
	}

	//Function that get skill information translation by language
    public function getSkillInformationTranslation($id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM skills_langs
            WHERE id_lang = '".$id_lang."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

	//Function to get skill information with translations
    public function getSkillInformationWithTranslations(){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = new \stdClass;

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $skill_translations = $this->getSkillInformationTranslation($lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($skill_translations)
                $data->{'lang_'.$lang->slug} = $skill_translations;
        }

        return $data;
    }

	//Function to get the skill by id
	public function getSkillById($id){

		$datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM skills
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
	}

	//Function to reorder skills positions
	public function reorderSkillsPosition($id, $position){

		//Getting all skills less the actual ID.
		$skills = $this->app['bd']->fetchObject("SELECT id, position FROM skills WHERE id != '".$id."' ORDER by position ASC");

		if(count($skills) > 0){
			$newPosition = 1;
			foreach($skills as $key => $skill){

				if($newPosition == $position){
					$newPosition++;
				}

				//Update the skill position
				$data = [];
				$data['position'] = $newPosition;

				$this->app['bd']->update("skills", $data, " id = '".$skill->id."'");

				$newPosition++;
			}
		}
	}

	//Function to delete all skill data
    public function deleteSkillById($id){

        //Getting skill
        $skill = $this->getSkillById($id);

        //Now deleting the icon of the skill
        if(isset($skill->icon) && $skill->icon != ""){
            if(file_exists(_PATH_.$skill->icon))
                unlink(_PATH_.$skill->icon);
        }

        //Finally deleting the skill from main table
        $this->app['bd']->query("DELETE FROM skills WHERE id = '".$id."'");
    }
}
