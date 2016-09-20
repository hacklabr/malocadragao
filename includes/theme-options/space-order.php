<?php
add_action('admin_menu', function (){
    $topLevelMenuLabel = 'Ordem dos Espaços';
    $page_title = 'Ordem dos Espaços';
    $menu_title = 'Ordem dos Espaços';

    $cb = function(){ render_virada_page_order_page(); };

    /* Top level menu */
    add_submenu_page('virada_space_order', $page_title, $menu_title, 'manage_options', 'virada_space_order', $cb);

    add_menu_page($topLevelMenuLabel, $topLevelMenuLabel, 'manage_options', 'virada_space_order', $cb);

    wp_enqueue_script('jquery-ui-sortable');
});


function render_virada_page_order_page(){
    $path = realpath(get_template_directory() . '/app') . '/';
    $filename =  $path . 'spaces-order.json';

		// date_default_timezone_set('America/Sao_Paulo');

		define('API_URL', get_theme_option('mapasculturais_api_url')); // "http://spcultura.prefeitura.sp.gov.br/api/");
		define('PROJECT_ID', get_theme_option('mapasculturais_project_id')); // 4);
		define('AGENT_IDS', get_theme_option('mapasculturais_agent_ids')); //'432,433,434');
		define('REPLACE_IMAGES_URL_FROM', get_theme_option('mapasculturais_images_url_from')); // 'http://spcultura.prefeitura.sp.gov.br//files/');
		define('REPLACE_IMAGES_URL_TO', get_theme_option('mapasculturais_images_url_to')); // 'http://viradacultural.prefeitura.sp.gov.br/imagens/');

		$project_id = PROJECT_ID;

		$date_from = get_theme_option('mapasculturais_date_from'); // '2016-04-28';
		$date_to = get_theme_option('mapasculturais_date_to'); // '2016-05-01';

		$children_project_ids = json_decode(file_get_contents(API_URL . "project/getChildrenIds/{$project_id}"));
		$children_project_ids[] = $project_id;

		$project_ids = implode(',',$children_project_ids);

		$get_spaces_url = API_URL . "space/findByEvents?@select=id,name,shortDescription,endereco,location&@files=(avatar.viradaSmall,avatar.viradaBig):url&@order=name&@from={$date_from}&@to={$date_to}&project=IN({$project_ids})";
		$get_events_url = API_URL . "event/find?@select=id,name,subTitle,shortDescription,description,classificacaoEtaria,terms,traducaoLibras,descricaoSonora,project.id,project.name,project.singleUrl&@files=(avatar.viradaSmall,avatar.viradaBig):url&project=IN({$project_ids})";

		echo "<br/> baixando eventos $get_events_url<br/> <br/> ";
		$events_json = file_get_contents($get_events_url);

		echo "<br/> baixando espaços $get_spaces_url<br/> <br/> ";
		$spaces_json = file_get_contents($get_spaces_url);

		$spaces = json_decode($spaces_json);
		$events = array();
		$events_by_id = array();

		$event_ids = [];

		foreach (json_decode($events_json) as $e) {
		    $events[] = $e;
		    $events_by_id[$e->id] = $e;
		    $event_ids[] = $e->id;
		}


		$result_events = array();

		if($event_ids){

		    $event_ids = implode(',', $event_ids);

		    $occurrences_json = file_get_contents(API_URL . "eventOccurrence/find?@select=id,space.id,eventId,rule&event=IN($event_ids)&@order=_startsAt");

		    $occurrences = json_decode($occurrences_json);


		    $count = 0;
		    foreach ($occurrences as $occ) {
		        $rule = $occ->rule;
		        $e = clone $events_by_id[$occ->eventId];
		        $e->id = $occ->id;
		        $e->eventId =  $occ->eventId;

		        $e->spaceId = $occ->space->id;
		        $e->startsAt = $rule->startsAt;
		        $e->startsOn = $rule->startsOn;

		        $datetime = new DateTime("{$rule->startsOn} {$rule->startsAt}");

		        $e->price = $rule->price;

		        $e->timestamp = $datetime->getTimestamp();

		        $e->duration = @$rule->duration;

		        if($e->duration == 1440){
		            $e->duration = '24h00';
		        }

		        $e->acessibilidade = array();
		        if($e->traducaoLibras)
		            $e->acessibilidade[] = 'Tradução para LIBRAS';

		        if($e->descricaoSonora)
		            $e->acessibilidade[] = 'Descrição sonora';


		        $small_image_property = '@files:avatar.viradaSmall';
		        $big_image_property = '@files:avatar.viradaBig';

		        if (property_exists($e, $small_image_property)) {
		            $e->defaultImage = str_replace(REPLACE_IMAGES_URL_FROM, REPLACE_IMAGES_URL_TO, $e->$big_image_property->url);
		            $e->defaultImageThumb = str_replace(REPLACE_IMAGES_URL_FROM, REPLACE_IMAGES_URL_TO, $e->$small_image_property->url);
		            $e->image768 = str_replace(REPLACE_IMAGES_URL_FROM, REPLACE_IMAGES_URL_TO, $e->$small_image_property->url);
		            $e->image800 = str_replace(REPLACE_IMAGES_URL_FROM, REPLACE_IMAGES_URL_TO, $e->$big_image_property->url);
		            $e->image1024 = str_replace(REPLACE_IMAGES_URL_FROM, REPLACE_IMAGES_URL_TO, $e->$big_image_property->url);
		            $e->image1280 = str_replace(REPLACE_IMAGES_URL_FROM, REPLACE_IMAGES_URL_TO, $e->$big_image_property->url);
		        } else {
		            $e->defaultImage = '';
		            $e->defaultImageThumb = '';
		        }

		        $result_events[] = $e;
		    }

		}

		file_put_contents($path . '/events.json', json_encode($result_events));
		file_put_contents($path . '/spaces.json', json_encode($spaces));

    // $spaces = json_decode(file_get_contents($path . 'spaces.json'));

    $mensagem = "";
    if($_POST){
        $json = json_encode(array_values($_POST['order']));
        file_put_contents($filename, $json);
        $mensagem = "Ordem Salva";
    }


    if(file_exists($filename)){
        $order = json_decode(file_get_contents($filename));
    }else{
        $order = array();
    }

    foreach($spaces as $i => $space)
        foreach($order as $o)
            if($space->id == $o->id)
                unset($spaces[$i]);


    foreach($spaces as $space){
        $obj = new stdClass;
        $obj->id = $space->id;
        $obj->name = $space->name;
        $order[] = $obj;
    }

    ?>
<style>
    .button-primary { position:fixed; top:75px; right:50px; }
    .js-sortable-item { cursor: move; }
</style>
<script type="text/javascript">
    (function($){
        $(function(){
            $('.js-sortable-container').sortable();
        });
    })(jQuery);
</script>
    <div class="wrap span-20">
        <h2>Ordem dos Espaços</h2>
        <?php if($mensagem): ?>
            <div class="updated below-h2">
                <p><?php echo $mensagem; ?></p>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="submit" class="button-primary" style="" value="Salvar Ordem" />
            <ul class="js-sortable-container">
              <?php foreach($order as $i => $sp): ?>
                <li class="js-sortable-item" >
                    <input type="hidden" name="order[<?php echo $i ?>][id]" value='<?php echo $sp->id; ?>' />
                    <input type="hidden" name="order[<?php echo $i ?>][name]" value='<?php echo $sp->name; ?>' />
                    <?php echo $sp->name; ?>
                </li>
              <?php endforeach; ?>
            </ul>
        </form>
    </div>

<?php }
