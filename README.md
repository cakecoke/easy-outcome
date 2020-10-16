### Easy outcome
Generate sync outcomes on the fly


#### installation
copy and modify ```.env.sample``` to ```.env```, then

```shell script
php composer.phar install
```

#### Usage
To get outcome from the console, use 

```shell script
./console build [dd.mm.yyyy]
```

produces output similar to 

```shell script
*Daily sync outcome*
BG-1111 [Grooming] always have custom subject line for... - @kir will write outcome of the investigation today
BG-2222 [Ready for Deploy] Contacts not being created... - @mike Code review
```

#### Slack bot

You can start the slack bot daemon by running
```shell script
./console serve
```
 




 
