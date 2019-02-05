<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Collection;
use Grav\Common\Uri;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Cache;

/**
 * Class LeagueManagerPlugin
 * @package Grav\Plugin
 */
class LeagueManagerPlugin extends Plugin
{

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onPageContentRaw' => ['onPageContentRaw', 0]
        ]);
    }

    private $CachedURIs = [];

    private function resolve($uri){

        $uri = trim($uri, '/');

        $cachepath = rtrim(CACHE_DIR, '/') . "/leaguemanager";

        if(!is_dir($cachepath)) {mkdir($cachepath); }

        $filename = str_replace('/','-', $uri) .'.json';

        if(array_key_exists($filename, $this->CachedURIs)) {
            return $this->CachedURIs[$filename];
        }

        if(file_exists($cachepath .'/'. $filename) && filemtime($cachepath .'/'. $filename) > strtotime("-1 hour")) {
            $result = file_get_contents($cachepath .'/'. $filename);
        }

        if(!isset($result) || empty($result)) {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://app.surpassport.com/api/'.$uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD,
                    $this->grav['config']->get('plugins.league.text_var'));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            $result = curl_exec($ch);
            $iinfo = curl_getinfo($ch);
            curl_close($ch);

            if(!empty($result)) {
                file_put_contents($cachepath .'/'. $filename, $result);

                $this->CachedURIs[$filename] = json_decode($result);
            }
        }

        return isset($result) ? json_decode($result) : false;
    }

    /**
     * Do some work for this event, full details of events can be found
     * on the learn site: http://learn.getgrav.org/plugins/event-hooks
     *
     * @param Event $event
     */
    public function onPageContentRaw(Event $event)
    {
        // Get the current raw content
        $content = $event['page']->getRawContent();

        $uri = $this->grav['uri'];

        $route = $this->config->get('plugins.leaguemanager.routes.leaguemanager');
        if ($route == $uri->path()) {

            $content = "";
            $obj = $this->resolve('seasons');

            $long = count($obj);
            for($ii=0; $ii<$long; $ii++) {
                $content = $content.'<a href="'.$obj[$ii]->uri.'">'.$obj[$ii]->label.'</a><br/>';
            }

            $event['page']->setRawContent('<h1>Seasons</h1>'.$content.
                '<script type="text/javascript">
                    document.title = "Seasons"
                </script>');

        } else if($route.'/' == substr($uri->path(), 0, 1+strlen($route))) {

            $path = explode('/',$uri->path());
            array_shift($path);

            //If we are looking for a season
            if (count($path) == 2 && $path[0] == 'season') {

                $rows = $this->resolve(join('/',$path()));

                if(!empty($rows)) {
                  $content = "";
                  for($ii=0; $ii<count($rows); $ii++) {
                      $content = $content.'<a href="/'.$route.'/'.$rows[$ii].'">'.(1+$ii).'</a><br/>';
                  }

                } else {
                  $content = "No active competitions found."
                }

                $event['page']->setRawContent('<h1>Season '.urldecode($path[1].'</h1>'.$content.
                  '<script type="text/javascript">document.title = "'.urldecode($path[1].'"</script>');

            }

            //If we are looking for a competition
            if (count($path) == 2 && [0] == 'competition') {
                $competition = $this->resolve(join('/',$path()));

                $divisions = "";
                if(!empty($competition->divisions)) for($ii=0; $ii<count($competition->divisions); $ii++) {
                    $divisions = $divisions.'<a href="/'.$route.'/'.$competition->divisions[$ii].'">'.(1+$ii).'</a><br/>';
                }

                $event['page']->setRawContent('<h1>'.$competition->name.'</h1>'.
                    '<b>Type:</b> '.$competition->type.'<br/>'.
                    '<b>Season:</b> '.$competition->season.'<br/>'.
                    '<b>Sport:</b> '.$competition->sport.'<br/>'.
                    '<br/>'.
                    $divisions.
                '<script type="text/javascript">
                    document.title = "'.$competition->name.'"
                </script>');

            }

            //If we are looking for a division
            if (count($path) == 2 && [0] == 'division') {

                $division = $this->resolve(join('/',$path()));

                $groupings = "";
                if(!empty($division->groupings)) for($ii=0; $ii<count($division->groupings); $ii++) {

                    $grouping = $this->resolve($division->groupings[$ii]);

                    if(isset($grouping->standings) && isset($grouping->standings->standings)) {

                        $groupings = $groupings.'<h3>'.$grouping->name.'</h3><br/><table style="margin: 0px auto;">
                            <tbody>
                            <tr>
                            <th>Position</th>
                            <th>Team</th>
                            <th>Played</th>
                            <th>Won</th>
                            <th>Lost</th>
                            <th>Drawn</th>
                            <th>Pts</th>
                            <th>Diff</th>
                            </tr>';


                        $long2 = count($grouping->standings->standings);
                        for($jj=0; $jj<$long2; $jj++) {
                            $team = $this->resolve($grouping->standings->standings[$jj]->team);

                            $groupings = $groupings.'<tr>
                                <td>'.(1+$jj).'</td>
                                <td>'.$team->name.'</td>
                                <td>'.$grouping->standings->standings[$jj]->played.'</td>
                                <td>'.$grouping->standings->standings[$jj]->won.'</td>
                                <td>'.$grouping->standings->standings[$jj]->lost.'</td>
                                <td>'.$grouping->standings->standings[$jj]->drawn.'</td>
                                <td>'.$grouping->standings->standings[$jj]->points.'</td>
                                <td>'.$grouping->standings->standings[$jj]->delta.'</td>';

                        }


                        $groupings = $groupings.'</tbody></table>';

                    }
                }

                $progression = "";
                if(!empty($division->progression)) {
                    $progression = '<br/><b>PROGRESSION RULES</b><br/>'.
                        'Finishing position ['.join(', ', $division->progression[0]->ranking).'] '.
                        'from ['.arary_map(function($G) {
                          $grouping = $this->resolve($G);
                          return $grouping->name;
                        }, $division->progression[0]->from).'] progress to <img width="10" src="'.self::ICONKNOCKOUT.'" /> '.$division->progression[0]->to;
                }

                $fixtures = "";
                $teams = "";

                $event['page']->setRawContent('<h1>'.$division->name.'</h1>'.$groupings.$progression.$fixtures.$teams.
                  '<script type="text/javascript">document.title = "'.$division->name.'"</script>');

            }
        }




    }

    const ICONKNOCKOUT = 'data:image/svg+xml;base64,
PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2ZXJzaW9uPSIxLjEiIGlkPSJDYXBhXzEiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNDczLjkzMiA0NzMuOTMyIiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA0NzMuOTMyIDQ3My45MzI7IiB4bWw6c3BhY2U9InByZXNlcnZlIiB3aWR0aD0iNTEyIiBoZWlnaHQ9IjUxMiIgY2xhc3M9IiI+PGcgdHJhbnNmb3JtPSJtYXRyaXgoLTEgMCAwIDEgNDczLjkzMiAwKSI+PGc+Cgk8Zz4KCQk8cGF0aCBzdHlsZT0iZmlsbDojMDEwMDAyIiBkPSJNMzg1LjUxMywzMDEuMjE0Yy0yNy40MzgsMC01MS42NCwxMy4wNzItNjcuNDUyLDMzLjA5bC0xNDYuNjYtNzUuMDAyICAgIGMxLjkyLTcuMTYxLDMuMy0xNC41NiwzLjMtMjIuMzQ3YzAtOC40NzctMS42MzktMTYuNDU4LTMuOTI2LTI0LjIyNGwxNDYuMDEzLTc0LjY1NmMxNS43MjUsMjAuOTI0LDQwLjU1MywzNC42LDY4Ljc0NiwzNC42ICAgIGM0Ny43NTgsMCw4Ni4zOTEtMzguNjMzLDg2LjM5MS04Ni4zNDhDNDcxLjkyNiwzOC42NTUsNDMzLjI5MiwwLDM4NS41MzUsMGMtNDcuNjUsMC04Ni4zMjYsMzguNjU1LTg2LjMyNiw4Ni4zMjYgICAgYzAsNy44MDksMS4zODEsMTUuMjI5LDMuMzIyLDIyLjQxMkwxNTUuODkyLDE4My43NGMtMTUuODMzLTIwLjAzOS00MC4wNzktMzMuMTU0LTY3LjU2LTMzLjE1NCAgICBjLTQ3LjcxNSwwLTg2LjMyNiwzOC42NzYtODYuMzI2LDg2LjM2OXMzOC42MTIsODYuMzQ4LDg2LjMyNiw4Ni4zNDhjMjguMjM2LDAsNTMuMDQzLTEzLjcxOSw2OC44MzItMzQuNjY0bDE0NS45NDgsNzQuNjU2ICAgIGMtMi4yODcsNy43NDQtMy45NDcsMTUuNzktMy45NDcsMjQuMjg5YzAsNDcuNjkzLDM4LjY3Niw4Ni4zNDgsODYuMzI2LDg2LjM0OGM0Ny43NTgsMCw4Ni4zOTEtMzguNjU1LDg2LjM5MS04Ni4zNDggICAgQzQ3MS45MDQsMzM5Ljg0OCw0MzMuMjcxLDMwMS4yMTQsMzg1LjUxMywzMDEuMjE0eiIgZGF0YS1vcmlnaW5hbD0iIzAxMDAwMiIgY2xhc3M9ImFjdGl2ZS1wYXRoIj48L3BhdGg+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+CjwvZz48L2c+IDwvc3ZnPg==';

}
