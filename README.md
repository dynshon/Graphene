# Graphene [Barium] beta
This framework allows you to create Action Oriented REST services with less lines of code, like this:

```PHP
class HelloWorld extends Action{
	public function run ()
	{
		$this->sendMessage('Hello world');
	}
}
```

## Install Graphene with composer

Graphene is can be installed using [composer](https://getcomposer.org/):

```bash
composer require graphene/graphene
```

after install you can bootstrap you application doing

```bash
cp ./vendor/graphene/graphene/_installation/* .
```

this command extracts from downloaded graphene library:
* __index.php__ Contains simple graphene launcher
* __settings.php__ Contains basic settings for logging and persistence
* __cli.php__ Simple CLI adapter for graphene
* __.htaccess__ Apache file with url rewriting and redirecting to `index.php`
* __web.config__  same with `.htaccess`, for windows ISS

## Graphene settings file
[work in progress]

## Action approach
Any http request to Graphene matches an "action". In graphene action mapping is quick and smart, ball actions are collected in separate modules.

### Defining module
you can define your module creating folder in your moduleUrl, definded in [settings.json](#setting-up-graphene).

```JSON
{
    "v" :  "0.1.1",
    "info": {
        "version"   : "0.0.0.1",
        "name"      : "com.profile",
        "namespace" : "profiles",
        "author"    : "Me [me@mail.com]",
        "support"   : "meMod.com"
    },
    "actions": []
}
```
in this case we have created a module named "com.profile", with "profiles" as namespace.
this module does not have any action.

### Creating actions
now we can ceate a simple action "HELLO_WORLD", mapping that on request *GET host/profiles/hello*
you can add this action creating this entry in your module manifest:
```JSON
{"name":"HELLO_WORLD", "query":"hello"}
```
and creating `profiles.HELLO_WORLD.php` file in `actions` folder like this:
```PHP
namespace profiles;

class HelloWorld extends Action{
    public function run ()
    {
        $this->sendMessage('Hello world');
    }
}
```
### Model
Graphene supports model checking and storage
_doc work in progress_

### Scaffolding
When you create a new module, we recomends this directory structure for your models and actions
```
_module namespace_
 +--manifest.json
 +--models
 |   |--ModelClassA.php 
 |   |--ModelClassB.php 
 |   |--Mod...
 +--actions
 |   |--namespace.ACTION_NAME_A.php 
 |   |--namespace.ACTION_NAME_B.php
 |   |--namespace...
```


## Wiki
we are very excited that you want to use Graphene therefore we are working so that you can use it to its full potential by writing up to date wiki.
[Go to Graphene wiki](https://github.com/marcomag89/Graphene/wiki)

### HowTo
**Hello world** [tutorial](https://github.com/marcomag89/Graphene/wiki/Hello-World-tutorial)
