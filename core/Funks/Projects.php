<?php
namespace Funks;

class Projects
{
	var $app;

	public function __construct($app)
	{
        $this->app = $app;
  	}

    /********************************************
	 *											*
	 * 			FUNCTIONS -> PROJECTS 		    *
	 *											*
     ********************************************/

    //Function to get projects stats
    public function getProjectsStats(){
		return $this->app['bd']->countRows("SELECT id FROM projects");
	}

    //Function to get last projects created
    public function getLastProjects($limit = 5) {

        $lang = $this->app['lang']->getDefaultLanguage();
        $languages = $this->app['lang']->getLanguagesVisibles();

        $projects = $this->app['bd']->fetchObject("
            SELECT 
                p.*,
                pcl.name AS category_name,
                pcl.slug AS category_slug,
                pc.badge as category_badge
            FROM projects p
            INNER JOIN project_lang pl ON p.id = pl.id_project AND pl.id_lang = '".$lang->id."'
            LEFT JOIN project_categories pc ON p.id_category = pc.id
            LEFT JOIN project_categories_lang pcl ON pcl.id_category = pc.id AND pcl.id_lang = '".$lang->id."'
            LEFT JOIN project_lang pl2 ON pl2.id_project = p.id
            WHERE 1 = 1
            GROUP BY p.id
            ORDER BY p.id DESC
            LIMIT $limit
        ");

        return $projects;
    }

    //Function to return all data from projects for language
    public function getProjectsByLang($slug_lang = ""){

        $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);

        //project BASE:: Images, title, category...
        $projects = $this->app['bd']->fetchObject("
            SELECT id, id_category, title, image_thumb, image_main, developed_in, technologies, url, position
            FROM projects
            WHERE visible = '1'
            ORDER BY position ASC
        ");

        //For each project we need his translation
        if(count($projects) > 0){
            foreach($projects as $key => $project){
                
                //Getting category
                $category = $this->getCategoryById($project->id_category, $id_lang);

                $project->category = new \stdClass();
                $project->category->slug = $category->slug;
                $project->category->name = $category->name;
                
                //Getting translations
                $translations = $this->getProjectTranslationById($project->id, $id_lang);

                $project->translation = new \stdClass();
                $project->translation->title = $translations->title;
                $project->translation->subtitle = $translations->subtitle;
                $project->translation->description_short = $translations->description_short;
                $project->translation->title_long = $translations->title_long;
                $project->translation->description_long = $translations->description_long;

                //Gallery
                $project->gallery = $this->getProjectGalleryById($project->id);
            }
        }

        return $projects;
    }

    //Function to get projects filtered
    public function getProjectsFiltered($comienzo, $limite, $applyLimit=true) {

        //Getting searchs params
		$filter_busqueda      = $this->app['tools']->getValue('search');
		$filter_id_category   = $this->app['tools']->getValue('id_category');
        $filter_visible     = $this->app['tools']->getValue('visible');

        //Where sqls additional
		$whereBusqueda = '';
		if ($filter_busqueda !== '') {
			$searchEscaped = addslashes($filter_busqueda);
			$whereBusqueda = "AND (p.title LIKE '%$searchEscaped%' OR pl.title LIKE '%$searchEscaped%' OR pl.subtitle LIKE '%$searchEscaped%' OR pl.description_short LIKE '%$searchEscaped%')";
		}

        $whereCategory = '';
		if ($filter_id_category !== 'all') {
			$whereCategory = "AND p.id_category = '$filter_id_category'";
		}

        $whereVisible = '';
		if ($filter_visible !== 'all') {
			$whereVisible = "AND p.visible = '$filter_visible'";
		}

        $lang = $this->app['lang']->getDefaultLanguage();
        $languages = $this->app['lang']->getLanguagesVisibles();

        $limitSql = $applyLimit ? "LIMIT $comienzo, $limite" : "";

        $projects = $this->app['bd']->fetchObject("
            SELECT 
                p.*,
                pcl.name AS category_name,
                pcl.slug AS category_slug,
                pc.badge as category_badge,
                GROUP_CONCAT(pl2.id_lang) AS available_languages
            FROM projects p
            INNER JOIN project_lang pl ON p.id = pl.id_project AND pl.id_lang = '".$lang->id."'
            LEFT JOIN project_categories pc ON p.id_category = pc.id
            LEFT JOIN project_categories_lang pcl ON pcl.id_category = pc.id AND pcl.id_lang = '".$lang->id."'
            LEFT JOIN project_lang pl2 ON pl2.id_project = p.id
            WHERE 1 = 1
            $whereBusqueda
            $whereCategory
            $whereVisible
            GROUP BY p.id
            ORDER BY p.position ASC
            $limitSql
        ");

        // Mapear idiomas disponibles por proyecto
        foreach($projects as $project) {
            //Languages
            $availableLangs = explode(',', $project->available_languages);
            $project->langs = [];
            foreach($languages as $language) {
                $project->langs[$language->slug] = in_array($language->id, $availableLangs);
            }
        }

        return $projects;
    }

    //Function to get project base by id
    public function getProjectBaseById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM projects
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function that get the project translation by id and language
    public function getProjectTranslationById($id_project, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM project_lang
            WHERE id_lang = '".$id_lang."'
            AND id_project  = '".$id_project."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get project with translations
    public function getProjectWithTranslations($id_project){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = new \stdClass;

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $project_translations = $this->getProjectTranslationById($id_project, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($project_translations)
                $data->{'lang_'.$lang->slug} = $project_translations;
        }

        return $data;
    }

    //Function to delete all project data
    public function deleteProjectById($id_project){

        //Getting project base info
        $project = $this->getProjectBaseById($id_project);

        //Deleting all gallery images associate to project
        $project_gallery = $this->getProjectGalleryById($id_project);

        if(count($project_gallery) > 0){
            foreach($project_gallery as $key => $gallery){

                //Deleting each image from project
                $this->deleteGalleryImageById($gallery->id, $id_project);
            }
        }

        //Deleting translations for this project
        $this->app['bd']->query("DELETE FROM project_lang WHERE id_project = '".$id_project."'");

        //Now deleting the images thumb and main for this project
        if(isset($project->image_thumb) && $project->image_thumb != ""){
            if(file_exists(_PATH_.$project->image_thumb))
                unlink(_PATH_.$project->image_thumb);
        }

        if(isset($project->image_main) && $project->image_main != ""){
            if(file_exists(_PATH_.$project->image_main))
                unlink(_PATH_.$project->image_main);
        }

        //Deleting the full directory for project
        $fullRoute = _PATH_ . "images/portfolio/".$id_project."/";
        if(file_exists($fullRoute) && is_dir($fullRoute)){
			rmdir($fullRoute);
        }

        //Finally deleting the project from main table
        $this->app['bd']->query("DELETE FROM projects WHERE id = '".$id_project."'");
    }

    /********************************************
	 *											*
	 * 			FUNCTIONS -> GALLERY 		    *
	 *											*
     ********************************************/

    //Function to get project gallery by id
    public function getProjectGalleryById($id){
        return $this->app['bd']->fetchObject("
            SELECT *
            FROM project_gallery
            WHERE id_project = '".$id."'
            ORDER BY creation_date ASC
        ");
    }

    //Function to delete gallery image of project
    public function deleteGalleryImageById($id, $id_project){

        $galleryImage = $this->app['bd']->fetchObject("SELECT * FROM project_gallery WHERE id = '".$id."' AND id_project = '".$id_project."'");
        if(count($galleryImage) > 0){
            $galleryImage = $galleryImage[0];
            if(isset($galleryImage->image) && $galleryImage->image != ""){
                if(file_exists(_PATH_.$galleryImage->image))
                    unlink(_PATH_.$galleryImage->image);
            }
        }

        $this->app['bd']->query("DELETE FROM project_gallery WHERE id = '".$id."' AND id_project = '".$id_project."'");
    }
    

	/********************************************
	 *											*
	 * 			FUNCTIONS -> CATEGORIES 		*
	 *											*
     ********************************************/

    //Function to get the different categories
    public function getCategoriesByLang($id_lang, $slug_lang = "", $getAllCategories = false){

        if($slug_lang != ""){
            $id_lang = $this->app['lang']->getCurrentLanguageId($slug_lang);
        }

         $sql = "
            SELECT cl.*, COUNT(p.id) AS total_projects
            FROM project_categories c
            JOIN project_categories_lang cl ON c.id = cl.id_category
            LEFT JOIN projects p ON p.id_category = c.id
            WHERE cl.id_lang = '".$id_lang."'
        ";

        if (!$getAllCategories)
            $sql .= "
                AND c.visible = '1'
                GROUP BY c.id
                HAVING total_projects > 0
            ";
        else 
            $sql .= " GROUP BY c.id ";

        $sql .= " ORDER BY c.id ASC";
        return $this->app['bd']->fetchObject($sql);
    }

    //Function to get categories filtered
    public function getCategoriesFiltered($comienzo, $limite, $applyLimit=true){

        //Obtenemos idioma default.
        $lang = $this->app['lang']->getDefaultLanguage();

        if($applyLimit)
            $limit = "LIMIT $comienzo, $limite";
        else
            $limit = "";

        $categories = $this->app['bd']->fetchObject("
            SELECT c.visible as visible, cl.*
            FROM project_categories c, project_categories_lang cl
            WHERE c.id = cl.id_category
            AND cl.id_lang = '".$lang->id."'
            ORDER BY c.id ASC
            $limit
        ");

        //Mapping to detect his different languages.
        if(count($categories) > 0){

            $languages = $this->app['lang']->getLanguagesVisibles();

            foreach($categories as $key => $category){

                $category->name 	= '';
                $category->slug 	= '';
                $category->langs    = [];

                //Getting projects associated to this category
                $category->projects = $this->app['bd']->countRows("SELECT id FROM projects WHERE id_category = '".$category->id_category."'");

                foreach($languages as $key => $language){

                    $langData = $this->app['bd']->fetchObject("SELECT * FROM project_categories_lang WHERE id_category  = '".$category->id_category."' AND id_lang = '".$language->id."'");

                    if(count($langData) == '1'){

                        //If we have data in the language, we get it.
                        $langData = $langData[0];
                        $category->langs[$language->slug] = true;

                        //Store the data in the object.
                        if(isset($language->is_default) && $language->is_default == 1){
                            $category->name = $langData->name;
                            $category->slug = $langData->slug;
                        }
                    }
                    else
                        $category->langs[$language->slug] = false;
                }
            }
        }

        return $categories;
    }

    //Function to get category base by id
    public function getCategoryBaseById($id){

        $datos = $this->app['bd']->fetchObject("
            SELECT *
            FROM project_categories
            WHERE id = '".$id."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function that get the category by id and language
    public function getCategoryById($id_category, $id_lang=""){

        //Si no proporciona idioma, obtenemos el default.
        if($id_lang == ''){
            $lang = $this->app['lang']->getDefaultLanguage();
            $id_lang = $lang->id;
        }

        $datos = $this->app['bd']->fetchObject("
            SELECT pc.badge as badge, pc.visible as visible, pcl.*
            FROM project_categories pc, project_categories_lang pcl
            WHERE pc.id = pcl.id_category 
            AND pcl.id_lang = '".$id_lang."'
            AND pcl.id_category  = '".$id_category."'
        ");

        if(count($datos) == '1')
            return $datos[0];
        else
            return false;
    }

    //Function to get category with translations
    public function getCategoryByIdWithTranslations($id){

        //Getting languages.
        $languages = $this->app['lang']->getLanguagesVisibles();

        $data = $this->getCategoryBaseById($id);

        foreach($languages as $key => $lang){

            //Get the data in the language.
            $category_translations = $this->getCategoryById($id, $lang->id);

            //Preparing the data with the languages
            $data->{'lang_'.$lang->slug} = [];

            if($category_translations)
                $data->{'lang_'.$lang->slug} = $category_translations;
        }

        return $data;
    }

    //Function to delete category
    public function deleteCategory($id){
        //Delete the category data and languages
        $this->app['bd']->query("DELETE FROM project_categories WHERE id = '".$id."'");
        $this->app['bd']->query("DELETE FROM project_categories_lang WHERE id_category = '".$id."'");
    }
}