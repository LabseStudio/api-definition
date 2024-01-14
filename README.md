# API Dictionnaire Wiktionnaire

> [!NOTE]
> Ce dépôt est un fork de [FredGainza/api-definition](https://github.com/FredGainza/api-definition).
> Il a pour but de modifier cette API pour qu'elle soit utilisée par [Remède](https://github.com/camarm-dev/remede)

> [!IMPORTANT]
> Pour citer ce projet, veuillez respecter la [licence](#-license-) et créditer "adaptation par `Labse Software` du projet original de `Fred Gainza`"

![Badge KoPaTiK](https://img.shields.io/badge/KoPaTiK-Agency-blue "Badge KoPaTiK")

API qui permet d'obtenir, via le [Wiktionnaire](https://fr.wiktionary.org), la définition des termes passés en input.


![Exemple de définition](assets/img/exemple-def.jpg "Exemple de définition obtenue")  

## *** Demo ***

Page de test [ici](https://api-definition.fgainza.fr)  


## *** Features ***

Les éléments extraits sont :

* l'url de la page web
* l'url de l'image
* la légende de l'image
* l'url du crédit de l'image

* classe(s) grammaticale(s) du terme recherché
* le genre (pour les noms et pour les adjectifs)
* les définitions associées (sans aucun exemple)

* message d'erreur si le parsing a échoué

**Features ajoutées par `Labse Software`**

* les balises `a` sont remplacées par `reference` pour permettre un parsing customizé
* exemples
* étymologie

```javascript
{
    "motWiki": "carpe",
        "error": "",
        "direct_link": "https://fr.wiktionary.org/wiki/carpe",
        "url_img": "",
        "legende_img": "",
        "url_credits": "",
        "nature": [
        "Nom commun 1",
        "Nom commun 2"
    ],
        "genre": [
        [
            "Nom commun 1",
            "féminin"
        ],
        [
            "Nom commun 2",
            "masculin"
        ]
    ],
        "natureDef": [
        [
            {
                "1": "(Zoologie) Poisson d’eau douce, de taille moyenne, originaire d'Asie (Chine surtout), de la famille des cyprinidés (Cyprinidae), comestible. ",
                "2": "(Cuisine) Chair cuisinée de ce poisson. ",
                "3": "(Zoologie) Variante de carpe commune (poisson). "
            },
            [
                {
                    "contenu": "(<span title=\"La zoologie est l’étude des animaux.\">Zoologie</span>)",
                    "sources": "(Franck Bouysse, <i>Grossir le ciel</i>, 2015, première partie, chapitre 8)"
                },
                {
                    "contenu": "Il pêchait parfois dans le canal situé au-dessus du moulin, alimenté par la rivière, dans les grands calmes où les rotengles, les poissons-chats et les <b>carpes</b> venaient se nourrir de grains concassés en balançant leurs flancs dans la lumière.",
                    "sources": "(Franck Bouysse, <i>Grossir le ciel</i>, 2015, première partie, chapitre 8)"
                },
                // Autres exemples réduits
            ]
        ],
        [
            {
                "1": "(Anatomie) Nom générique des huit petits os du poignet. "
            },
            [
                {
                    "contenu": "(<span title=\"L’anatomie est la description structurelle du corps.\" id=\"fr-anatomie\">Anatomie</span>)",
                    "sources": "(Collectif, <i>Abattage et Transformation des viandes de boucherie: Les produits élaborés à base de viande</i>, Educagri Editions, 2001, page 15)"
                },
                {
                    "contenu": "Pièce de l'avant, la raquette a comme base osseuse les <b>carpes</b>, le radius, le cubitus, l'humérus, le scapulum.",
                    "sources": "(Collectif, <i>Abattage et Transformation des viandes de boucherie: Les produits élaborés à base de viande</i>, Educagri Editions, 2001, page 15)"
                }
            ]
        ]
    ]
}
```  


## *** Suppléments ***

2 cas particuliers pour lesquels des liens internes ont été ajoutés :

* les mots pluriels (lien vers la définition du mot au singulier)
* les verbes conjugués (lien vers la définition du verbe à l'infinitif)

Cela permet d'éviter d'avoir pour seule définition "Pluriel de ..." ou "3eme personne du singulier du verbe ..."


![Exemple de définition](assets/img/exemple-pluriel.jpg "Exemple de double définition")  

## *** Déployer avec Docker ***

```shell
docker build -t remede-definition-api .
```

```shell
docker run -p 8089:80 remede-definition-api
```

- Visiter [localhost:8089](http://localhost:8089)

## *** Langages et bibliothèques utilisés ***

* Bootstrap
* PHP avec bibliothèque "simple_html_dom"
* Ajax JQuery   


## *** Auteur ***

* **Frédéric Gainza** _alias_ [@FredGainza](https://github.com/FredGainza)  


## *** License ***

Licence ``GNU General Public License v3.0`` - voir [LICENSE](LICENSE) pour plus d'informations
