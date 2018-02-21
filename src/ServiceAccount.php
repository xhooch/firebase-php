<?php
namespace Plokko\Firebase;

use Google\Auth\CredentialsLoader;
use Google\Auth\FetchAuthTokenCache;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use UnexpectedValueException;

class ServiceAccount
{
    private
        /**@var array $authConfig json-decoded service account data **/
        $authConfig,
        /**@var CacheItemPoolInterface $cache Caching interface**/
        $cache;

    /**
     * ServiceAccount constructor.
     * @param string|array $scope
     * @param string|array $authConfig
     */
    function __construct($authConfig)
    {
        if (is_string($authConfig)) {
            if (file_exists($authConfig)) {
                if (!is_array($authConfig = json_decode(file_get_contents($authConfig), true))) {
                    throw new LogicException('invalid json for FCM auth config');
                }
            } elseif (!is_array($authConfig = json_decode($authConfig, true))){
                throw new InvalidArgumentException('FCM auth config file not found');
            }
        }

        if(empty($authConfig['type']) || $authConfig['type']!=='service_account'){
            throw new InvalidArgumentException('Invalid service account data!');
        }

        $this->authConfig   = $authConfig;

    }

    /**
     * Get the credentials
     * @param $scope array|string Scope of the requested credentials @see https://developers.google.com/identity/protocols/googlescopes
     * @return \Google\Auth\Credentials\ServiceAccountCredentials|\Google\Auth\Credentials\UserRefreshCredentials|FetchAuthTokenCache
     */
    protected function getCredentials($scope){
        $creds = CredentialsLoader::makeCredentials($scope, $this->authConfig);
        //OAuth token caching
        if (!is_null($this->cache)) {
            $creds = new FetchAuthTokenCache($creds, [], $this->cache);
        }
        return $creds;
    }

    /**
     * Authorize an http request
     * @param array|string $scope Scope of the requested credentials @see https://developers.google.com/identity/protocols/googlescopes
     * @param ClientInterface|null $request
     * @return ClientInterface
     */
    function authorize($scope,ClientInterface $request=null){
        $config = $request? $request->getConfig():[];
        $creds = $this->getCredentials($scope);
        return CredentialsLoader::makeHttpClient($creds,$config);
    }

    /**
     * Return the Firebase project id
     * @return string
     */
    function getProjectId(){
        if(empty($this->authConfig['project_id'])){
            throw new UnexpectedValueException('project_id not found in auth config file!');
        }
        return $this->authConfig['project_id'];
    }


    function setCacheHandler(CacheItemPoolInterface $cache){
        $this->cache = $cache;
    }
}