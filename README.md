
# SLUG
* Service Levels Under Guest: RESTful routing access to execute Linux shell scripts

* A Docker based PHP application for running server-side scripts via custom URLs.

## Note: Both of the DockerHub images, SLUG PHP (jenmcquade/slug:latest) and SLUG NGINX (jenmcquade/slug:nginx) are built from Dockerfiles contained in this repository.  You can modify these Dockerfile build instructions to copy your scripts into the Alpine-based Docker image during a build.  Use these images to run your own customized SLUG service environment, without the need to copy your scripts from another location.

## Use Cases
1.  Host a service shortcut that curls an outside vendor's API and returns a JSON response.
2.  Access Linux command-line environment tools on SLUG without needing to install them on your production server
3.  Preserve a collection of microservice solutions in an environment that can easily be installed on any host with Docker Machine installed.
4.  Create a bundle of URL shortcuts to automation scripts that you can share with your team, hosted on your machine or in the cloud.
5.  Provide a generic service gateway to mask the use of multiple languages and technologies

## Suggestions
I suggest that developers do not use this application environment to host a UI or Database.  In its purest form, SLUG provides a manageable way to interface with other applications (in the same or other environments).  Using promises in your Web Service calls can allow you to manage an initial transaction to your database or UI, then while this connection is open, you can pass arguments to your scripts hosted on SLUG via port :9082, to further process or cleanse your transaction data before returning it to the source application.  

You could also use this environment as an intermediary platform for external API calls, allowing you to preserve a URL context that maps to your company's domain without exposing your external dependencies.  The slug.json configuration file makes it easy to manage what scripts are accessible to the public.

## To create a SLUG environment for your own scripts:

1. Clone the project into your solution and run 'docker-compose up'
#### Example
```
$ git clone https://github.com/jenmcquade/SLUG && cd SLUG
$ docker-compose up
```
2. Move your shell scripts into the 'sample' folder or modify ./docker-compose.yml to map the Docker container's ./slug directory to a different folder on your host machine.

3. Modify ./slug/sample/slug.json to point to your scripts.  Your scripts will have URL formats resembling the JSON structure of slug.json, like this:
http://localhost:9082/{{App Namespace}}/{{App Name}}/action/{{Script Name}}

Example URL for accessing the Star Wars API shortcut (swapi.bash) script from the './slug/sample/collection1/apps' directory:
http://localhost:9082/my_app_collections/app1/action/star_wars

4. Your scripts can accept any ammount of stringed arguments and flags by using the format of "?arg1=Foo&flag1=-Bar&arg2=Foo2&flag2=--Name"
Example URL for querying the Star Wars API script with arguments (will return Luke, Anakin and Shmi):
http://localhost:9082/my_app_collections/app1/action/star_wars?arg1=people&arg2=Skywalker

5. Once you have created a service application that you'd like to use in your solution, you can run 'docker-compose bundle' to generate a .dab file for release to another environment, which will include your own code as well as instructions for creating containers in your environment.  Or, you can build from docker-compose_build.yml, to build and publish your own Docker images for SLUG PHP and Nginx containers, in your own swarm setup. More information about setting alternate Docker Compose files can be found here:
https://docs.docker.com/compose/reference/overview/

  #### Example
  ```
  docker-compose -f docker-compose_build.yml build
  ```

