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
            $info = curl_getinfo($ch);
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
     * @param Event $e
     */
    public function onPageContentRaw(Event $e)
    {
        // Get the current raw content
        $content = $e['page']->getRawContent();

        $uri = $this->grav['uri'];

        $route = $this->config->get('plugins.leaguemanager.routes.league');
        if ($route == $uri->path()) {
            $e['page']->setRawContent('<h1>League</h1>');
        }


        $route = $this->config->get('plugins.leaguemanager.routes.seasons');
        if ($route == $uri->path()) {


            $content = "";
            $obj = $this->resolve('seasons');

            $long = count($obj);
            for($i=0; $i<$long; $i++) {
                $content = $content.'<a href="'.$obj[$i]->uri.'">'.$obj[$i]->label.'</a><br/>';
            }

            $e['page']->setRawContent('<h1>Seasons</h1>'.$content.
                '<script type="text/javascript">
                    document.title = "Seasons"
                </script>');

        } else {

            $uriseason = explode('/',$uri->path());
            $hostname = 'http://'.getenv('HTTP_HOST');

            //If we are looking for a season
            if ($uriseason[1] == 'season') {
                $uriseasonname = explode('+',$uriseason[2]);
                $longname = count($uriseasonname);
                $uriseasontitle = '';
                for($i=0; $i<$longname; $i++) {
                    if ($uriseasonname[$i]!='+'){
                        $uriseasontitle = $uriseasontitle.$uriseasonname[$i].' ';
                    }
                }


                $obj2 = $this->resolve($uri->path());

                $content = "";
                $long = count($obj2);
                for($i=0; $i<$long; $i++) {
                    $f = $i+1;
                    $content = $content.'<a href="'.$hostname.'/'.$obj2[$i].'">'.$f.'</a><br/>';
                }




                $e['page']->setRawContent('<h1>Season '.$uriseasontitle.'</h1>'.$content.
                '<script type="text/javascript">
                    document.title = "'.$uriseasontitle.'"
                </script>');

            }

            //If we are looking for a competition
            if ($uriseason[1] == 'competition') {

                $obj3 = $this->resolve($uri->path());

                $content = "";
                $long = count($obj3->divisions);
                for($i=0; $i<$long; $i++) {
                    $f = $i+1;
                    $content = $content.'<a href="'.$hostname.'/'.$obj3->divisions[$i].'">'.$f.'</a><br/>';
                }

                $e['page']->setRawContent('<h1>'.$obj3->name.'</h1>'.
                    '<b>Type:</b> '.$obj3->type.'<br/>'.
                    '<b>Season:</b> '.$obj3->season.'<br/>'.
                    '<b>Sport:</b> '.$obj3->sport.'<br/>'.
                    '<br/>'.
                    $content.
                '<script type="text/javascript">
                    document.title = "'.$obj3->name.'"
                </script>');

            }

            //If we are looking for a division
            if ($uriseason[1] == 'division') {

                $obj4 = $this->resolve($uri->path());

                $names = array();

                $content = "";
                $long = count($obj4->groupings);
                for($i=0; $i<$long; $i++) {
                    $f = $i+1;

                    $obj5 = $this->resolve($obj4->groupings[$i]);

                    $names[$i] = $obj5->name;

                    if(isset($obj5->standings) && isset($obj5->standings->standings)){


                    $content = $content.'<h3>'.$obj5->name.'</h3><br/><table style="margin: 0px auto;">
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


                    $long2 = count($obj5->standings->standings);
                    for($j=0; $j<$long2; $j++) {
                        $g = $j+1;

                        $obj6 = $this->resolve($obj5->standings->standings[$j]->team);

                        $content = $content.'<tr>
                            <td>'.$g.'</td>
                            <td>'.$obj6->name.'</td>
                            <td>'.$obj5->standings->standings[$j]->played.'</td>
                            <td>'.$obj5->standings->standings[$j]->won.'</td>
                            <td>'.$obj5->standings->standings[$j]->lost.'</td>
                            <td>'.$obj5->standings->standings[$j]->drawn.'</td>
                            <td>'.$obj5->standings->standings[$j]->points.'</td>
                            <td>'.$obj5->standings->standings[$j]->delta.'</td>';

                    }


                    $content = $content.'</tbody></table>';

                }

                }


                //$names = ['A','B'];

                $e['page']->setRawContent('<h1>'.$obj4->name.'</h1>'.$content.
                    '<br/><b>PROGRESSION RULES</b><br/>'.
                    '['.$obj4->progression[0]->ranking[0].' & '.$obj4->progression[0]->ranking[1].'] from ['.$names[0].' & '.$names[1].'] progress to <img width="10" src="data:image/svg+xml;base64,
PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2ZXJzaW9uPSIxLjEiIGlkPSJDYXBhXzEiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNDczLjkzMiA0NzMuOTMyIiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA0NzMuOTMyIDQ3My45MzI7IiB4bWw6c3BhY2U9InByZXNlcnZlIiB3aWR0aD0iNTEyIiBoZWlnaHQ9IjUxMiIgY2xhc3M9IiI+PGcgdHJhbnNmb3JtPSJtYXRyaXgoLTEgMCAwIDEgNDczLjkzMiAwKSI+PGc+Cgk8Zz4KCQk8cGF0aCBzdHlsZT0iZmlsbDojMDEwMDAyIiBkPSJNMzg1LjUxMywzMDEuMjE0Yy0yNy40MzgsMC01MS42NCwxMy4wNzItNjcuNDUyLDMzLjA5bC0xNDYuNjYtNzUuMDAyICAgIGMxLjkyLTcuMTYxLDMuMy0xNC41NiwzLjMtMjIuMzQ3YzAtOC40NzctMS42MzktMTYuNDU4LTMuOTI2LTI0LjIyNGwxNDYuMDEzLTc0LjY1NmMxNS43MjUsMjAuOTI0LDQwLjU1MywzNC42LDY4Ljc0NiwzNC42ICAgIGM0Ny43NTgsMCw4Ni4zOTEtMzguNjMzLDg2LjM5MS04Ni4zNDhDNDcxLjkyNiwzOC42NTUsNDMzLjI5MiwwLDM4NS41MzUsMGMtNDcuNjUsMC04Ni4zMjYsMzguNjU1LTg2LjMyNiw4Ni4zMjYgICAgYzAsNy44MDksMS4zODEsMTUuMjI5LDMuMzIyLDIyLjQxMkwxNTUuODkyLDE4My43NGMtMTUuODMzLTIwLjAzOS00MC4wNzktMzMuMTU0LTY3LjU2LTMzLjE1NCAgICBjLTQ3LjcxNSwwLTg2LjMyNiwzOC42NzYtODYuMzI2LDg2LjM2OXMzOC42MTIsODYuMzQ4LDg2LjMyNiw4Ni4zNDhjMjguMjM2LDAsNTMuMDQzLTEzLjcxOSw2OC44MzItMzQuNjY0bDE0NS45NDgsNzQuNjU2ICAgIGMtMi4yODcsNy43NDQtMy45NDcsMTUuNzktMy45NDcsMjQuMjg5YzAsNDcuNjkzLDM4LjY3Niw4Ni4zNDgsODYuMzI2LDg2LjM0OGM0Ny43NTgsMCw4Ni4zOTEtMzguNjU1LDg2LjM5MS04Ni4zNDggICAgQzQ3MS45MDQsMzM5Ljg0OCw0MzMuMjcxLDMwMS4yMTQsMzg1LjUxMywzMDEuMjE0eiIgZGF0YS1vcmlnaW5hbD0iIzAxMDAwMiIgY2xhc3M9ImFjdGl2ZS1wYXRoIj48L3BhdGg+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+Cgk8Zz4KCTwvZz4KCTxnPgoJPC9nPgoJPGc+Cgk8L2c+CjwvZz48L2c+IDwvc3ZnPg==" /> '.$obj4->progression[0]->to.
                '<script type="text/javascript">
                    document.title = "'.$obj4->name.'"
                </script>');

            }
        }




    }
}
