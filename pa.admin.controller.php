<?php
    /**
     * @class  paAdminController
     * @author kimdongmin (kdm3843@gmail.com)
     * @brief  pa module admin controller class
     **/

    class paAdminController extends pa {

        /**
         * @brief initialization
         **/
        function init() {
        }

        /**
         * @brief insert borad module
         **/
        function procPaAdminInsertPa($args = null) {
            // igenerate module model/controller object
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            // setup the pa module infortmation
            $args = Context::getRequestVars();
            $args->module = 'pa';
            $args->mid = $args->pa_name;
			if(is_array($args->use_status)) $args->use_status = implode('|@|', $args->use_status);
            unset($args->pa_name);

            // setup other variables
            if($args->except_notice!='Y') $args->except_notice = 'N';
            if($args->use_anonymous!='Y') $args->use_anonymous= 'N';
            if($args->consultation!='Y') $args->consultation = 'N';
            if(!in_array($args->order_target,$this->order_target)) $args->order_target = 'list_order';
            if(!in_array($args->order_type,array('asc','desc'))) $args->order_type = 'asc';

            // if there is an existed module
            if($args->module_srl) {
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
                if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
            }

            // insert/update the pa module based on module_srl
            if(!$args->module_srl) {
            	$args->hide_category = 'N';
                $output = $oModuleController->insertModule($args);
                $msg_code = 'success_registed';
            } else {
            	$args->hide_category = $module_info->hide_category;
                $output = $oModuleController->updateModule($args);
                $msg_code = 'success_updated';
            }

            if(!$output->toBool()) return $output;

			// setup list config
			$list = explode(',',Context::get('list'));
			if(count($list))
			{
				$list_arr = array();
				foreach($list as $val)
				{
					$val = trim($val);
					if(!$val) continue;
					if(substr($val,0,10)=='extra_vars') $val = substr($val,10);
					$list_arr[] = $val;
				}
				$oModuleController = &getController('module');
				$oModuleController->insertModulePartConfig('pa', $output->get('module_srl'), $list_arr);
			}

            $this->setMessage($msg_code);
			if (Context::get('success_return_url')){
				changeValueInUrl('mid', $args->mid, $module_info->mid);
				$this->setRedirectUrl(Context::get('success_return_url'));
			}else{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPaAdminPaInfo', 'module_srl', $output->get('module_srl')));
			}
        }

		/**
		 * Pa info update in basic setup page
		 * @return void
		 */
		public function procPaAdminUpdatePaFroBasic()
		{
			$args = Context::getRequestVars();

			// for pa info
			$args->module = 'pa';
			$args->mid = $args->pa_name;
			if(is_array($args->use_status))
			{
				$args->use_status = implode('|@|', $args->use_status);
			}
			unset($args->pa_name);

			if(!in_array($args->order_target, $this->order_target))
			{
				$args->order_target = 'list_order';
			}
			if(!in_array($args->order_type, array('asc', 'desc')))
			{
				$args->order_type = 'asc';
			}

			$oModuleController = &getController('module');
			$output = $oModuleController->updateModule($args);

			// for grant info, Register Admin ID
			$oModuleController->deleteAdminId($args->module_srl);
			if($args->admin_member)
			{
				$admin_members = explode(',',$args->admin_member);
				for($i=0;$i<count($admin_members);$i++)
				{
					$admin_id = trim($admin_members[$i]);
					if(!$admin_id) continue;
					$oModuleController->insertAdminId($args->module_srl, $admin_id);
				}
			}
		}

        /**
         * @brief delete the pa module
         **/
        function procPaAdminDeletePa() {
            $module_srl = Context::get('module_srl');

            // get the current module
            $oModuleController = &getController('module');
            $output = $oModuleController->deleteModule($module_srl);
            if(!$output->toBool()) return $output;

            $this->add('module','pa');
            $this->add('page',Context::get('page'));
            $this->setMessage('success_deleted');
        }

		function procPaAdminSaveCategorySettings()
		{
			$module_srl = Context::get('module_srl');
			$mid = Context::get('mid');

			$oModuleModel = getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if($module_info->mid != $mid)
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$module_info->hide_category = Context::get('hide_category') == 'Y' ? 'Y' : 'N';
			$oModuleController = getController('module'); /* @var $oModuleController moduleController */
			$output = $oModuleController->updateModule($module_info);
			if(!$output->toBool())
			{
				return $output;
			}

			$this->setMessage('success_updated');
			if (Context::get('success_return_url'))
			{
				$this->setRedirectUrl(Context::get('success_return_url'));
			}
			else
			{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPaAdminCategoryInfo', 'module_srl', $output->get('module_srl')));
			}
		}
    }
?>
