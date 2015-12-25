# SJMForm

A PHP class to generate HTML forms from a form markup language with c-like syntax. 

This repository was created for my [blog post](http://heap.ch/blog/2015/12/25/sjmform/) which contains additional information. Check out the [interactive demo](http://demo.heap.ch/SJMForm/demo.php).

## Usage

The SJMForm class not only compiles the form markup language to HTML, it also provides some help with handling form submissions and putting data into the forms. 

```php
<?php
// allocate new form generator with form markup language
$form = new SJMForm($fml);
// get sanitized entered form data
$input = $form->preprocess($_POST);
// fill form with data from database
$form->set($data);
// compile form
echo $form;
```

After creating a SJMForm object, the `preprocess` member function can be used to get all the values with corresponding form keys from a POST or GET request. The preprocessed data can then be saved in a database or put back into the form with the `set` method, e.g. to allow a user to edit a registration. The `__toString` member function handles the HTML conversion automatically when the object is converted to a string, which happens by calling `echo`. 

## Syntax

Form inputs are defined in the following form:

```c
type name(arguments) {
	option name(arguments);
	...
};
```

`type` is one of the predefined input generators and `name` is the internal input name used by the HTML form. A complete form definition might look something like this:

```c
text name("Name", "Bob");

password pw("Password");

hidden secret("schmikret");

textarea comments("Comments");

checkbox skills("Coding Skills") {
	option c("C");
	option php("PHP", true);
	option java("Java", true);
};

radio department("Department") {
	option other("other", true);
	option itet("D-ITET");
	option infk("D-INFK");
};

select rating("Rating") {
	option 0("0");
	option 1("1");
	option 2("2");
	option 3("3");
	option 4("4");
	option 5("5", true);
	option 6("6");
	option 7("7");
};
```

All available types are used in the example. For the type "hidden", the first argument is the default value. For all other types, the first argument is the display name, and the second an optional default value. 





