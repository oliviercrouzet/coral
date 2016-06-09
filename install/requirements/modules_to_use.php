<?php
function register_modules_to_use_requirement()
{
	$MODULE_VARS = [
		"uid" => "modules_to_use",
		"translatable_title" => _("Modules to use"),
		"hide_from_completion_list" => true,
	];

	return array_merge( $MODULE_VARS,[
		"installer" => function($shared_module_info) use ($MODULE_VARS) {
			$return = new stdClass();
			$return->yield = new stdClass();
			$return->yield->title = _("Modules to use");
			$return->success = true;

			$module_list = $shared_module_info["module_list"];
			$modules_not_to_install = [];
			foreach ($module_list as $i => $mod)
			{
				$mod_chosen = null;
				// We can only auto-set if there is no alternative and mod is required
				if ($mod["required"] && !isset($mod["alternative"]))
				{
					$mod_chosen = true;
					$return->success &= true;
				}
				else if (isset($_POST[$mod["uid"]]))
				{
					$mod_chosen = $_POST[$mod["uid"]] == 1;
					$return->success &= true;
				}

				if ($mod_chosen !== null || isset($_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ]["useModule"]))
				{
					$mod_chosen = $mod_chosen !== null ? $mod_chosen : $_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ]["useModule"];
					if (!isset($_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ]))
					{
						$_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ] = [];
					}
					$_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ]["useModule"] = $mod_chosen;
					$shared_module_info["setSharedModuleInfo"]($MODULE_VARS["uid"], $mod["uid"], ["useModule" => $mod_chosen]);
					$module_list[$i]["default_value"] = $mod_chosen;
					if (!$mod_chosen)
						$modules_not_to_install[] = $mod["uid"];
				}
				else
				{
					// If the associated session variable is still unset the setup has failed but why?
					$return->messages[] = "For some reason at least one ($mod[uid]) of these variables is not set. There may a problem with the installer please contact the programmers with this error message.";
					$return->success &= false;
				}
			}
			// Ensure that required modules that are not enabled have alternatives set
			foreach ($module_list as $mod)
			{
				//only bother if the module is required and has an alternative
				if (isset($mod["alternative"]))
				{
					//only check if the alternative is being invoked (i.e. module not used)
					if (isset($_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ]["useModule"]) && !$_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ]["useModule"])
					{
						$alternative_vars = array_map(function($key){
							return $key;
						}, array_keys($mod["alternative"]));
						foreach ($alternative_vars as $v) {
							if (!empty($_POST[ "$mod[uid]_$v" ]))
							{
								$_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ][$v] = $_POST[ "$mod[uid]_$v" ];
								$shared_module_info["setSharedModuleInfo"]( $mod["uid"], "alternative", [$v => $_POST[ "$mod[uid]_$v" ]]);
							}
							if (isset($_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ][$v]))
							{
								$shared_module_info["setSharedModuleInfo"]( $mod["uid"], "alternative", [  $v => $_SESSION[ $MODULE_VARS["uid"] ][ $mod["uid"] ][$v]  ]);
							}
							else
							{
								if ($mod["required"])
								{
									$return->yield->messages[] = sprintf(_("You must either enable the module %s or provide alternative details."), $mod["title"]);
									$return->success &= false;
								}
							}
						}
					}
				}
			}

			//check that all dependencies are met
			if (count($modules_not_to_install) > 0)
			{
				$dep_list = [];
				// build dependency list
				foreach ($module_list as $mod) {
					if (!isset($mod["dependencies_array"]))
					continue;
					$dep_list = array_unique(array_merge($dep_list, $mod["dependencies_array"]));
				}
				foreach ($modules_not_to_install as $mod) {
					if (in_array($mod, $dep_list))
					{
						$mod_title = array_values(array_filter($module_list, function ($m) use ($mod) {
							return $m["uid"] == $mod;
						}))[0]["title"];
						$return->yield->messages[] = sprintf(_("The modules that you have chosen to install work with additional modules. You need to add '%s'"), $mod_title);
						$return->success = false;
					}
				}
			}

			if (!$return->success)
			{
				require "install/templates/modules_to_use_template.php";
				$return->yield->body = modules_to_use_template($module_list);
			}
			return $return;
		}
	]);
}
