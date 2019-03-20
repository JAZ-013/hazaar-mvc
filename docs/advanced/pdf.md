# PDF Controller Responses

PDFs can be generated using a controller response. This approach has been used because to output a PDF you don't want to output anything else, otherwise the browser will get confused. The PDF Response controller is called Hazaar\Controller\Response\PDF. You instantiate the object, load in some HTML and return that from your controller action so that it can automatically be rendered and returned to the browser.

```php
class GetPDFController extends Hazaar\Controller\Action {
    public function index(){
        $pdf = new Hazaar\Controller\Response\PDF();
        $pdf->setContent("<h1>This is a test PDF</h1>");
        return $pdf;
    }
}
```

That's it! Then, when you navigate your browser to http://yourhost.com/GetPDF you will download a PDF file with the content This is a test PDF in header format. The HTML that can be rendered into a PDF can be as simple or as complex as you need it. The renderer will resolve images and stylesheets and render the PDF as accurately as possible.

## Rendering from a file

So say you have lots of HTML and you don't want embed that in your code.  Fair enough, just load it from a file that is stored somewhere like the view directory.

```php
class GetPDFController extends Hazaar\Controller\Action {
    public function index(){
        $pdf = new Hazaar\Controller\Response\PDF();
        $content = file_get_contents(Hazaar\Loader::getFilePath(FILE_PATH_VIEW, 'pdf/testpdf.html'));
        $pdf->setContent($content);
        return $pdf;
    }
}
```

## Rendering from a template

Now that you have gotten this far, you are bored with generating static PDFs and want to easily embed some data into your documents.  This is actually pretty easy using Hazaar MVCs built in SMARTy template engine.

Here, we just load the template from a view file into a new SMARTy template object and render the output.

```php
class GetPDFController extends Hazaar\Controller\Action {
    public function index(){
        $pdf = new Hazaar\Controller\Response\PDF();
        $content = file_get_contents(Hazaar\Loader::getFilePath(FILE_PATH_VIEW, 'pdf/testpdf.html'));
        $template = new Hazaar\Template\Smarty($content);
        $data = array('header' => 'This is a header!', 'string' => 'This is a string of text!');
        $pdf->setContent($template->render($data);
        return $pdf;
    }
}
```

## Rendering a Web Page

Yes, this is definitely possible. It is done using some built-in features of Wkhtmltopdf to download the HTML from the website.
If you take the above example you can modify it as below:

```php
class GetPDFController extends Hazaar\Controller\Action {
    public function index(){
        $pdf = new Hazaar\Controller\Response\PDF();
        $pdf->setSource("http://www.google.com");
        return $pdf;
    }
}
```

This will automatically download the source HTML and render it.

This approach is recommended over using file_get_contents("http://www.google.com") as it will correctly resolve URLs. Loading and passing the HTML to Hazaar\Controller\Response\PDF::setContent() will not do this.

## Installing Wkhtmltopdf

HazaarMVC uses the wkhtmltopdf program to do the actual rendering as it does a far superior job to any other HTML to PDFrenderer we have come across.
You can read up on wkhtmltopdf on the wkhtmltopdf Google Code page.

There are two ways to install the executable.

### Automated Installation

If the executable does not exist, HazaarMVC will try and download and install it automatically. This can fail for many reasons, such as HazaarMVC not having write permissions to the Support directory. If it does fail, HazaarMVC will attempt to figure out what needs to be done and throw a descriptive error with instructions. With any luck, this will 'just work' and you may not even notice that wkhtmltopdf was not installed. ;)

### Manual Installation

It's still pretty easy to manually install wkhtmltopdf. Simply follow these steps:

1. Download the tar file appropraite for your hosts CPU architecture (i386 or amd64) from http://code.google.com/p/wkhtmltopdf/downloads/list
2. Untar the executable into HAZAAR_BASE/Hazaar/Libs. So if your HazaarMVC installation is in /usr/share/libhazaar-framework and you are on a 64-bit OS, you would run:

```
tar xjf wkhtmltopdf-0.11.0_rc1-static-amd64.tar.bz2 --directory /usr/share/libhazaar-framework/Hazaar/Support
```

That's it. If it's in the right place and executable, everything should start working.