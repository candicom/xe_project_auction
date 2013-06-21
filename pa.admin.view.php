<?php
    /**
     * @class  paAdminView
     * @author kimdongmin (kdm3843@gmail.com)
     * @brief  pa module admin view class
     **/

    class paAdminView extends pa {

        /**
         * @brief initialization
         *
         * pa module can be divided into general use and admin use.\n
         **/
        function init() {
            // check module_srl is existed or not
            $module_srl = Context::get('module_srl');
            if(!$module_srl && $this->module_srl) {
                $module_srl = $this->module_srl;
                Context::set('module_srl', $module_srl);
            }

            // generate module model object
            $oModuleModel = &getModel('module');

            // get the module infomation based on the module_srl
            if($module_srl) {
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
                if(!$module_info) {
                    Context::set('module_srl','');
                    $this->act = 'list';
                } else {
                    ModuleModel::syncModuleToSite($module_info);
                    $this->module_info = $module_info;
					$this->module_info->use_status = explode('|@|', $module_info->use_status);
                    Context::set('module_info',$module_info);
                }
            }

            if($module_info && $module_info->module != 'pa') return $this->stop("msg_invalid_request");

            // get the module category list
            $module_category = $oModuleModel->getModuleCategories();
            Context::set('module_category', $module_category);

			$security = new Security();
			$security->encodeHTML('module_info.');
			$security->encodeHTML('module_category..');

            // setup template path (pa admin panel templates is resided in the tpl folder)
            $template_path = sprintf("%stpl/",$this->module_path);
            $this->setTemplatePath($template_path);

            // install order (sorting) options
            foreach($this->order_target as $key) $order_target[$key] = Context::getLang($key);
            $order_target['list_order'] = Context::getLang('document_srl');
            $order_target['update_order'] = Context::getLang('last_update');
            Context::set('order_target', $order_target);
        }

        /**
         * @brief display the pa module admin contents
         **/
        function dispPaAdminContent() {
            // setup the pa module general information
			$args = new stdClass();
            $args->sort_index = "module_srl";
            $args->page = Context::get('page');
            $args->list_count = 20;
            $args->page_count = 10;
            $args->s_module_category_srl = Context::get('module_category_srl');

			$search_target = Context::get('search_target');
			$search_keyword = Context::get('search_keyword');

			switch ($search_target){
				case 'mid':
					$args->s_mid = $search_keyword;
					break;
				case 'browser_title':
					$args->s_browser_title = $search_keyword;
					break;
			}

            $output = executeQueryArray('pa.getPaList', $args);
            ModuleModel::syncModuleToSite($output->data);

			// get the skins path
            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);

			$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);

            // get the layouts path
            $oLayoutModel = &getModel('layout');
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);

			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);

			$oModuleAdminModel = &getAdminModel('module');
            $selected_manage_content = $oModuleAdminModel->getSelectedManageHTML($this->xml_info->grant);
            Context::set('selected_manage_content', $selected_manage_content);

            // use context::set to setup variables on the templates
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('pa_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);

			$security = new Security();
			$security->encodeHTML('pa_list..browser_title','pa_list..mid');
			$security->encodeHTML('skin_list..title','mskin_list..title');
			$security->encodeHTML('layout_list..title','layout_list..layout');
			$security->encodeHTML('mlayout_list..title','mlayout_list..layout');

            // 템플릿 파일 지정
            $this->setTemplateFile('index');
        }

        /**
         * @brief display the selected pa module admin information
         **/
        function dispPaAdminPaInfo() {
            $this->dispPaAdminInsertPa();
        }

        /**
         * @brief display the module insert form
         **/
        function dispPaAdminInsertPa() {
            if(!in_array($this->module_info->module, array('admin', 'pa','blog','guestbook'))) {
                return $this->alertMessage('msg_invalid_request');
            }

            // get the skins list
            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);

			$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);

            // get the layouts list
            $oLayoutModel = &getModel('layout');
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);

			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);

			$security = new Security();
			$security->encodeHTML('skin_list..title','mskin_list..title');
			$security->encodeHTML('layout_list..title','layout_list..layout');
			$security->encodeHTML('mlayout_list..title','mlayout_list..layout');

			// get document status list
			$oDocumentModel = &getModel('document');
			$documentStatusList = $oDocumentModel->getStatusNameList();
			Context::set('document_status_list', $documentStatusList);

            $oPaModel = &getModel('pa');

            // setup the extra vaiables
            Context::set('extra_vars', $oPaModel->getDefaultListConfig($this->module_info->module_srl));

            // setup the list config (install the default value if there is no list config)
            Context::set('list_config', $oPaModel->getListConfig($this->module_info->module_srl));

			$security = new Security();
			$security->encodeHTML('extra_vars..name','list_config..name');

            // set the template file
            $this->setTemplateFile('pa_insert');
        }

        /**
         * @brief display the additional setup panel
         * additonal setup panel is for connecting the service modules with other modules
         **/
        function dispPaAdminPaAdditionSetup() {
            // sice content is obtained from other modules via call by reference, declare it first
            $content = '';

            // get the addtional setup trigger
            // the additional setup triggers can be used in many modules
            $output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'before', $content);
            $output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'after', $content);
            Context::set('setup_content', $content);

            // setup the template file
            $this->setTemplateFile('addition_setup');
        }

        /**
         * @brief display the pa mdoule delete page
         **/
        function dispPaAdminDeletePa() {
            if(!Context::get('module_srl')) return $this->dispPaAdminContent();
            if(!in_array($this->module_info->module, array('admin', 'pa','blog','guestbook'))) {
                return $this->alertMessage('msg_invalid_request');
            }

            $module_info = Context::get('module_info');

            $oDocumentModel = &getModel('document');
            $document_count = $oDocumentModel->getDocumentCount($module_info->module_srl);
            $module_info->document_count = $document_count;

            Context::set('module_info',$module_info);

			$security = new Security();
			$security->encodeHTML('module_info..mid','module_info..module','module_info..document_count');

            // setup the template file
            $this->setTemplateFile('pa_delete');
        }

        /**
         * @brief display category information
         **/
        function dispPaAdminCategoryInfo() {
            $oDocumentModel = &getModel('document');
            $category_content = $oDocumentModel->getCategoryHTML($this->module_info->module_srl);
            Context::set('category_content', $category_content);

            Context::set('module_info', $this->module_info);
            $this->setTemplateFile('category_list');
        }

        /**
         * @brief display the grant information
         **/
        function dispPaAdminGrantInfo() {
            // get the grant infotmation from admin module
            $oModuleAdminModel = &getAdminModel('module');
            $grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
            Context::set('grant_content', $grant_content);

            $this->setTemplateFile('grant_list');
        }

        /**
         * @brief display extra variables
         **/
        function dispPaAdminExtraVars() {
            $oDocumentAdminModel = &getModel('document');
            $extra_vars_content = $oDocumentAdminModel->getExtraVarsHTML($this->module_info->module_srl);
            Context::set('extra_vars_content', $extra_vars_content);

            $this->setTemplateFile('extra_vars');
        }

        /**
         * @brief display the module skin information
         **/
        function dispPaAdminSkinInfo() {
             // get the grant infotmation from admin module
            $oModuleAdminModel = &getAdminModel('module');
            $skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
            Context::set('skin_content', $skin_content);

            $this->setTemplateFile('skin_info');
        }

        /**
         * Display the module mobile skin information
         **/
        function dispPaAdminMobileSkinInfo() {
             // get the grant infotmation from admin module
            $oModuleAdminModel = &getAdminModel('module');
            $skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
            Context::set('skin_content', $skin_content);

            $this->setTemplateFile('skin_info');
        }

        /**
         * @brief pa module message
         **/
        function alertMessage($message) {
            $script =  sprintf('<script> xAddEventListener(window,"load", function() { alert("%s"); } );</script>', Context::getLang($message));
            Context::addHtmlHeader( $script );
        }
    }
?>
