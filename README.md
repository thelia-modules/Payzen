    ____                            
    |  _ \ __ _ _   _ _______ _ __  
    | |_) / _` | | | |_  / _ \ '_ \ 
    |  __/ (_| | |_| |/ /  __/ | | |
    |_|   \__,_|\__, /___\___|_| |_|
                |___/               
                             ______         _____ _          _ _
                             | ___ \       |_   _| |        | (_)
                             | |_/ /_   _    | | | |__   ___| |_  __ _
                             | ___ \ | | |   | | | '_ \ / _ \ | |/ _` |
                             | |_/ / |_| |   | | | | | |  __/ | | (_| |
                             \____/ \__, |   \_/ |_| |_|\___|_|_|\__,_|
                                     __/ |
                                    |___/   <info@thelia.net>



----------

Ce module vous permet de proposer à vos clients le système de paiement PayZen de la société Lyra Networks.

### Installation

Pour installer le module PayZen, décompressez l'archive dans `<racine de thelia>/local/modules`. Veillez à ce que le dossier porte le nom `PayZen` (et pas `PayZen-master`, par exemple).

**Warning**: Attention, si vous mettez à jour manuellement une version pre-1.3 vers une version 1.3 ou ultérieure, vous devrez
supprimer manuellement le répertoire AdminIncludes du module, sinon des problèmes pourraient survenir lors de la configuration
du module.  

### Utilisation

Pour utiliser le module PayZen, vous devez tout d'abord le configurer. Pour ce faire, rendez-vous dans votre back-office, onglet Modules, et activez le module PayZen.

Cliquez ensuite sur "Configurer" sur la ligne du module, et renseignez les informations requises, que vous trouverez dans votre outil de gestion de caisse PayZen -&gt; Paramétrage -&gt; Boutiques -&gt; *votre boutique*

Lors de la phase de test, vous pouvez définir les adresses IP qui seront autorisées à utiliser le module en front-office, afin de ne pas laisser vos clients payer leur commandes avec PayZen pendant la phase de test.

### URL de retour

Pour que vos commandes passent automatiquement au statut payé lorsque vos clients ont payé leurs commandes, vous devez renseigner une **URL de retour** dans votre outils de gestion de caisse PayZen.

Cette adresse est formée de la manière suivante: `http://www.votresite.com/payzen/callback`
Par exemple, pour le site `thelia.net`, l'adresse en mode test et en mode production serait: `http://www.thelia.net/payzen/callback`. 

Vous trouverez l'adresse exacte à utiliser dans votre back-office Thelia, sur la page de configuration du module PayZen.

Pour mettre en place cette URL de retour rendez-vous dans votre outil de gestion de caisse PayZen -&gt; Paramétrage -&gt; Boutiques -&gt; *votre boutique*, et copier/collez votre URL de retour dans les champs "*URL de retour de la boutique en mode test*" et "*URL de retour de la boutique en mode production*".

### Intégration en front-office

L'intégration est automatique, et s'appuie sur les templates standard.

### Paiement en plusieurs fois

PayZen propose le paiement en plusieurs fois. Vous pouvez le proposer à vos clients en installant le module Thelia **PayZenMulti**.

### Paiement par prélèvement SEPA (SDD)

PayZen propose le paiement par prélèvement SEPA. Vous pouvez le proposer à vos clients en installant le module Thelia **PayZenOneOffSEPA**.

----------

This module offers to your customers the PayZen payment system, operated by the Lyra Networks compagny.

### Installation

To install the PayZen module, uncompress the archive in the `<thelia root>/local/modules` directory. Be sure that the name of the module's directory is `PayZen` (and not `PayZen-master`, for exemple).

**Warning**: If you're updating by manually a pre-1.3 version to a 1.3.x version, be sure to remove the AdminIncludes directory, or you'll get
ikssues in the module configuration.

### Usage

You have to configure the PayZen module before starting to use it. To do so, go to the "Modules" tab of your Thelia back-office, and activate the PayZen module.

Then click the "Configure" button, and enter the required information, which are available in your PayZen back-office -&gt; Setting -&gt; Shops -&gt; *your shop*

During the test phase, you can defin the IP addresses allowed to use the PayZen module on the front office, so that your customers will not be able to pay with PayZen during this test phase.

### Return URL

For your order switching automatically to the "paid" status when your customers have successfully completed their payment, you should provide a **return URL** in the PayZen back-office.

The return URL has the following form: `http://www.yourshop.com/payzen/callback`. For example, the return URL of the `thelia.net` is `http://www.thelia.net/payzen/callback`. 

You'll find the exact return URL of you shop in the Thelia back-office, in the PayZen configuration page.

To set up this return URL, go to your PayZen back-office, -&gt; Setting -&gt; Shops -&gt; *your shop*, and paste your return URL in "*Shop's return URL in test mode*" et "*Return URL of the shop in production mode*" fields.

### Front-office integration

The front-office integration is automatic, as it relies on standard templates.

### Multiple times payments

Multiple time payments are possible with PayZen. To offer this feature to your customers, install the Thelia **PayZenMulti** module on your shop.

### SEPA payment (SDD)

SEPA payments are possible with PayZen. To offer this feature to your customers, install the Thelia **PayZenOneOffSEPA** module on your shop.
