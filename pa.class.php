<?php
    /**
     * @class  pa
     * @author kimdongmin (kdm3843@gmail.com)
     * @brief  pa module high class
     **/

    class pa extends ModuleObject {

        var $search_option = array('title','content','title_content','comment','user_name','nick_name','user_id','tag'); ///< 검색 옵션

        var $order_target = array('list_order', 'update_order', 'regdate', 'voted_count', 'blamed_count', 'readed_count', 'comment_count', 'title'); // 정렬 옵션

        var $skin = "default"; ///< skin name
        var $list_count = 20; ///< the number of documents displayed in a page
        var $page_count = 10; ///< page number
        var $category_list = NULL; ///< category list


        /**
         * @brief install the module
         **/
        function moduleInstall() {
            // use action forward(enabled in the admin model)
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            // 2007. 10. 17 insert member menu trigger
            $oModuleController->insertTrigger('member.getMemberMenu', 'pa', 'controller', 'triggerMemberMenu', 'after');

            // install pa module
            $args->site_srl = 0;
            $output = executeQuery('module.getSite', $args);
            if(!$output->data->index_module_srl) {
                $args->mid = 'pa';
                $args->module = 'pa';
                $args->browser_title = 'Working with Experts';
                $args->skin = 'default';
                $args->site_srl = 0;
                $output = $oModuleController->insertModule($args);
                if($output->toBool())
                {
	                $module_srl = $output->get('module_srl');
	                $site_args->site_srl = 0;
	                $site_args->index_module_srl = $module_srl;
	                $oModuleController = &getController('module');
	                $oModuleController->updateSite($site_args);
                }
            }

            return new Object();
        }

        /**
         * @brief chgeck module method
         **/
        function checkUpdate() {
            $oModuleModel = &getModel('module');

            // 2007. 10. 17 get the member menu trigger
            if(!$oModuleModel->getTrigger('member.getMemberMenu', 'pa', 'controller', 'triggerMemberMenu', 'after')) return true;

            // 2011. 09. 20 when add new menu in sitemap, custom menu add
            if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'pa', 'model', 'triggerModuleListInSitemap', 'after')) return true;
            return false;
        }

        /**
         * @brief update module
         **/
        function moduleUpdate() {
            $oModuleModel = &getModel('module');
            $oModuleController = &getController('module');

            // 2007. 10. 17  check the member menu trigger, if it is not existed then insert
            if(!$oModuleModel->getTrigger('member.getMemberMenu', 'pa', 'controller', 'triggerMemberMenu', 'after'))
                $oModuleController->insertTrigger('member.getMemberMenu', 'pa', 'controller', 'triggerMemberMenu', 'after');

            // 2011. 09. 20 when add new menu in sitemap, custom menu add
            if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'pa', 'model', 'triggerModuleListInSitemap', 'after'))
                $oModuleController->insertTrigger('menu.getModuleListInSitemap', 'pa', 'model', 'triggerModuleListInSitemap', 'after');

            return new Object(0, 'success_updated');
        }

		function moduleUninstall() {
			$output = executeQueryArray("pa.getAllPa");
			if(!$output->data) return new Object();
			@set_time_limit(0);
			$oModuleController =& getController('module');
			foreach($output->data as $pa)
			{
				$oModuleController->deleteModule($pa->module_srl);
			}
			return new Object();
		}

        /**
         * @brief re-generate the cache files
         **/
        function recompileCache() {
        }

    }
?>
