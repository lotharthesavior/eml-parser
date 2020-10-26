# Email file parser to HTML or PDF

This command line file converts email content into **html ** or **pdf**.



## Installation

After cloning it into a directory, run this inside that directory:

```shell
composer install
```



## Usage

To use this tool just run the following from inside the directory:

```shell
php parse-email.php {pdf|html}
```

The files to be processed must be in the directory `./emails` and the output will be places in the directory `./output`.



## Logs

Every request will generate a log at the file `./result.log`.

