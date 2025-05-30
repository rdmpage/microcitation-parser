# Microcitation parser


Tools to parse "microcitations" to literature in the Biodiversity Heritage Library (BHL) and elsewhere.

## SQLite and Heroku

Because of the size of the SQLite database it is managed using LFS. This posses problems for Heroku as it doesn’t recognise LFS by default. To get this to work we do the following:

- Add `https://github.com/infinitly/heroku-buildpack-git-lfs` as a build pack, see [infinitly/heroku-buildpack-git-lfs](https://github.com/infinitly/heroku-buildpack-git-lfs)
- We create a personal access token for GitHub, see https://github.com/settings/tokens
- We add this repository to the project as the value for the environment variable (AKA config key) `GIT_LFS_REPOSITORY` as `https://<token>@github.com/rdmpage/<repository>.git`.

Now Heroku will load the database file using LFS and build the app.

## BHL database

In the `data` directory there is a simple JSON file that stores titles and the corresponding BHL TitleID. There his no attempt to clean or normalise the titles, we simply match what we have. In the development version we can add new matches to this file.

There is also a SQLite database of BHL data. The table `title` is generated by fetching the file [title.txt](https://www.biodiversitylibrary.org/data/hosted/title.txt) from BHL, extracting just the three columns that we need using `php parse-titles.php > titles.tsv` then using `php import-titles.php` to load that into SQLite.

## ISSN database

The `data/issn` directory has several TSV files listing journal names and ISSNs. These can be used to create a local database to look up ISSNs.

## Works database

The `data/works` directory has several TSV files listing basic metadata for “works” (i.e., articles). These can be used to create a local database to look up DOIs based on simple metadata.

### SQL to generate TSV file

```
SELECT issn, series, volume, issue, spage, epage, year, doi FROM publications_doi WHERE issn IN ("0960-4286","1474-0036");
```

### SQL to update records with missing data

If we subsequently update the external source database(s), for example, by adding series numbers or pagination, we can generate SQL update statements to move this to the database used by this service.

#### Series

```
SELECT DISTINCT "UPDATE works SET series=""" || series || """ WHERE volume=""" || volume || """ AND issue=""" || issue || """ AND year=""" || year || """ AND issn=""" || issn || """;"  FROM publications_doi WHERE issn='0374-5481' AND series IS NOT NULL;
```

#### spage

```
SELECT DISTINCT "UPDATE works SET spage=""" || spage || """ WHERE doi=""" || doi || """;" FROM publications_doi WHERE issn='1175-5334' AND spage IS NOT NULL;
```

#### epage

```
SELECT DISTINCT "UPDATE works SET epage=""" || epage || """ WHERE doi=""" || doi || """;" FROM publications_doi WHERE issn='1175-5334' AND epage IS NOT NULL;
```
