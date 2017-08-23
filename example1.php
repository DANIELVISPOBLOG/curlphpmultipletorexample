<?php
        /*
         * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
         * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
         * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
         * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
         * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
         * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
         * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
         * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
         * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
         * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
         * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
         *
         * This software is licensed under the MIT license. For more information,
         * see <https://www.vispo.org>.
         *
         * @license http://www.opensource.org/licenses/mit-license.html  MIT License
         * @author: Daniel Vispo
         * URL: https://www.vispo.org
         * Date: 2017/08/21
         * Desc: How to use 5 Tor proxies at the same time from PHP and
         *       how to renew the Tor circuits by using the Control Ports
         */

        define('__TORPROXIES__',array(
                array(
                        'strHost'=>'127.0.0.1',
                        'intSOCKSPort'=>8000,
                        'intControlPort'=>9000
                ),
                array(
                        'strHost'=>'127.0.0.1',
                        'intSOCKSPort'=>8001,
                        'intControlPort'=>9001,
                ),
                array(
                        'strHost'=>'127.0.0.1',
                        'intSOCKSPort'=>8002,
                        'intControlPort'=>9002,
                ),
                array(
                        'strHost'=>'127.0.0.1',
                        'intSOCKSPort'=>8003,
                        'intControlPort'=>9003,
                ),
                array(
                        'strHost'=>'127.0.0.1',
                        'intSOCKSPort'=>8004,
                        'intControlPort'=>9004,
                )
        ));
        
        define('__CURLHEADERS__',array(
                array(
                        'Accept-Encoding: gzip, deflate',
                        'Connection: keep-alive',
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/603.3.8 (KHTML, like Gecko) Version/10.1.2 Safari/603.3.8',
                        'Accept-Language: ja-jp',
                        'Referer: https://www.google.co.jp/',
                        'Dnt: 1'
                ),
                array(
                        'Accept-Encoding: gzip, deflate, br',
                        'Connection: keep-alive',
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36',
                        'Accept-Language: en-US,en;q=0.8',
                        'Cache-control:no-cache',
                        'Referer: https://www.google.com/'
                ),
                array(
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246',
                        'Accept: text/html,application/xhtml+x…lication/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language: es-ES,en;q=0.5',
                        'Accept-Encoding: gzip, deflate, br',
                        'Referer: https://www.google.es/',
                        'Connection: keep-alive',
                        'Upgrade-Insecure-Requests: 1',
                        'Cache-Control: max-age=0'
                )
        ));

        $intCurrentTorUsedIdx=-1;
        
        function HTTPGetTor($strURL,$booTorEnabled=TRUE,$booTorRenew=TRUE){
                $intReturn=-1;
                try{
                        if (is_null($strURL) || strlen(trim($strURL))==0){
                                throw new Exception('Param $strURL is 0 chars length or is null.');
                        }else{
                                $hndCurl = curl_init($strURL);
                                if ($booTorEnabled){
                                        $intCurrentTorUsedIdx=$GLOBALS['intCurrentTorUsedIdx']=$GLOBALS['intCurrentTorUsedIdx']+1;
                                        if ($intCurrentTorUsedIdx==(count(__TORPROXIES__)))$intCurrentTorUsedIdx=$GLOBALS['intCurrentTorUsedIdx']=0;
                                        curl_setopt($hndCurl, CURLOPT_PROXY, __TORPROXIES__[$intCurrentTorUsedIdx]['strHost'].':'.__TORPROXIES__[$intCurrentTorUsedIdx]['intSOCKSPort']);
                                        curl_setopt($hndCurl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                                }
                                curl_setopt($hndCurl, CURLOPT_HEADER, FALSE);
                                curl_setopt($hndCurl, CURLINFO_HEADER_OUT, TRUE);
                                curl_setopt($hndCurl, CURLOPT_VERBOSE, TRUE);
                                curl_setopt($hndCurl, CURLOPT_FOLLOWLOCATION, TRUE);
                                curl_setopt($hndCurl, CURLOPT_HTTPHEADER, __CURLHEADERS__[array_rand(__CURLHEADERS__,1)]);
                                curl_setopt($hndCurl, CURLOPT_ENCODING, 1);
                                curl_setopt($hndCurl, CURLOPT_RETURNTRANSFER, TRUE);
                                curl_setopt($hndCurl, CURLOPT_TIMEOUT,60);
                                curl_setopt($hndCurl, CURLOPT_FRESH_CONNECT, TRUE);
                                $strResponse=curl_exec($hndCurl);
                                $intSize=curl_getinfo($hndCurl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                                $strHTTPReqHeaders=curl_getinfo($hndCurl, CURLINFO_HEADER_OUT);
                                $arrHTTPReqHeaders=array_filter(array_filter(explode(PHP_EOL,$strHTTPReqHeaders),'trim'),'strlen');
                                $strContentType=curl_getinfo($hndCurl, CURLINFO_CONTENT_TYPE);
                                $intHTTPCode=curl_getinfo($hndCurl, CURLINFO_HTTP_CODE);
                                $intReturn=array('intHTTPCode'=>$intHTTPCode,'strContentType'=>$strContentType,'strResponse'=>$strResponse,'intSize'=>$intSize,'arrHTTPReqHeaders'=>$arrHTTPReqHeaders);
                                curl_close($hndCurl);
                                if ($booTorEnabled && $booTorRenew){
                                        $hndControlPort=fsockopen(__TORPROXIES__[$intCurrentTorUsedIdx]['strHost'],__TORPROXIES__[$intCurrentTorUsedIdx]['intControlPort'],$errNo,$errStr,30);
                                        if (!$hndControlPort){
                                                throw new Exception('Cannot Connect to Control Port. Tor Proxy '.__TORPROXIES__[$intCurrentTorUsedIdx]['strHost'].':'.__TORPROXIES__[$intCurrentTorUsedIdx]['intControlPort']);
                                        }else{
                                                fputs($hndControlPort,"AUTHENTICATE \r\n");
                                                $strResponse=fread($hndControlPort,1024);
                                                list($strCode,$strText)=explode(' ',$strResponse,2);
                                                if ($strCode!='250'){
                                                        fclose($hndControlPort);
                                                        throw new Exception('Cannot Authenticate. Tor Proxy '.__TORPROXIES__[$intCurrentTorUsedIdx]['strHost'].':'.__TORPROXIES__[$intCurrentTorUsedIdx]['intControlPort']);
                                                }else{
                                                        fputs($hndControlPort,"SIGNAL NEWNYM\r\n");
                                                        $strResponse=fread($hndControlPort,1024);
                                                        list($strCode,$strText)=explode(' ',$strResponse,2);
                                                        if ($strCode!='250'){
                                                                fclose($hndControlPort);
                                                                throw new Exception('Cannot Renew Circuit. Tor Proxy '.__TORPROXIES__[$intCurrentTorUsedIdx]['strHost'].':'.__TORPROXIES__[$intCurrentTorUsedIdx]['intControlPort']);
                                                        }else{
                                                                fclose($hndControlPort);
                                                        }
                                                }
                                        }
                                }
                        }
                }catch(Exception $e){
                        throw new Exception('('.__FUNCTION__.') - '.$e->getMessage());
                }
                return $intReturn;
        }
        
        function getIPGeo($booTorEnabled=TRUE,$booTorRenew=TRUE){
                try{
                        $arrHTTPResponse=HTTPGetTor('http://ip-api.com/php/',$booTorEnabled,$booTorRenew);
                        if ($arrHTTPResponse['intHTTPCode']!=200){
                                throw new Exception('HTTP Response Code isn\'t 200');
                        }else{
                                if ($arrHTTPResponse['intSize']==0){
                                        throw new Exception('HTTP Response Size is zero bytes');
                                }else{
                                        if ($arrHTTPResponse['strContentType']!='text/plain; charset=utf-8'){
                                                throw new Exception('HTTP Response Content-type isn\'t valid');
                                        }else{
                                                $strResponse=unserialize($arrHTTPResponse['strResponse']);
                                                if (!is_array($strResponse)){
                                                        throw new Exception('HTTP Response isn\t array after unserialize');
                                                }else{
                                                        if (!isset($strResponse['query'])){
                                                                throw new Exception('HTTP Unserialized Response doesn\'t have query property');
                                                        }else{
                                                                echo "\t- IP: \033[32m".$strResponse['query']."\033[30m".PHP_EOL;
                                                                if (!isset($strResponse['city'])){
                                                                        throw new Exception('HTTP Unserialized Response doesn\'t have city property');
                                                                }else{
                                                                        echo "\t- City: ".$strResponse['city'].PHP_EOL;
                                                                        if (!isset($strResponse['country'])){
                                                                                throw new Exception('HTTP Unserialized Response doesn\'t have country property');
                                                                        }else{
                                                                                echo "\t- Country: ".$strResponse['country'].PHP_EOL;
                                                                                if (count($arrHTTPResponse['arrHTTPReqHeaders'])==0){
                                                                                        throw new Exception('HTTP Response arrHTTPReqHeaders is an empty array');
                                                                                }else{
                                                                                        for ($i=0;$i<count($arrHTTPResponse['arrHTTPReqHeaders']);$i++){
                                                                                                echo "\t- HTTP Request Header ".$i.": ".$arrHTTPResponse['arrHTTPReqHeaders'][$i].PHP_EOL;
                                                                                        }
                                                                                }
                                                                        }
                                                                }
                                                        }
                                                }
                                        }
                                }

                        }
                }catch(Exception $e){
                        throw new Exception('('.__FUNCTION__.') - '.$e->getMessage());
                }
        }

        //MAIN
        try{
                echo "\033[30m";
                echo "\033[35m+ Get Public IP and its Geolocation\033[30m".PHP_EOL;
                getIPGeo(FALSE);
                for ($i=0;$i<count(__TORPROXIES__);$i++){
                        $intIdx=$intCurrentTorUsedIdx+1;
                        if ($intIdx>count(__TORPROXIES__)-1)$intIdx=0;
                        echo "\033[34m+ Get IP and its Geolocation through Tor Proxy ".__TORPROXIES__[$intIdx]['strHost'].":".__TORPROXIES__[$intIdx]['intSOCKSPort']."\033[30m".PHP_EOL;
                        getIPGeo();
                }
                echo "\033[30m";

        }catch(Exception $e){
                echo "\033[31m\t- ERROR: ".$e->getMessage()."\033[30m".PHP_EOL;
                echo "\033[30m";
        }
