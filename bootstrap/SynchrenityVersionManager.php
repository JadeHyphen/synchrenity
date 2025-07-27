<?php
// bootstrap/SynchrenityVersionManager.php
namespace Synchrenity\Bootstrap;

class SynchrenityVersionManager
{
    protected $composerFile;

    public function __construct($composerFile = null)
    {
        $this->composerFile = $composerFile ?: __DIR__ . '/../composer.json';
    }

    public function getVersion()
    {
        $data = json_decode(file_get_contents($this->composerFile), true);
        return $data['version'] ?? '0.0.0';
    }

    public function bump($type = 'patch')
    {
        $version = $this->getVersion();
        list($major, $minor, $patch) = explode('.', $version);
        switch ($type) {
            case 'major': $major++; $minor = 0; $patch = 0; break;
            case 'minor': $minor++; $patch = 0; break;
            case 'patch': default: $patch++; break;
        }
        $newVersion = "$major.$minor.$patch";
        $data = json_decode(file_get_contents($this->composerFile), true);
        $data['version'] = $newVersion;
        file_put_contents($this->composerFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $newVersion;
    }
}
