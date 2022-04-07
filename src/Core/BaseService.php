<?php namespace SamagTech\Core;

use CodeIgniter\I18n\Time;
use CodeIgniter\CodeIgniter;
use Psr\Log\LoggerInterface;
use SamagTech\Core\BaseModel;
use CodeIgniter\Entity\Entity;
use SamagTech\ExcelLib\Writer;
use SamagTech\ExcelLib\Factory as ExcelFactory;
use SamagTech\Contracts\Service;
use CodeIgniter\HTTP\IncomingRequest;
use SamagTech\Crud\Config\BaseAppConfig;
use SamagTech\Crud\Singleton\CurrentUser;
use SamagTech\Exceptions\CreateException;
use SamagTech\Exceptions\DeleteException;
use SamagTech\Exceptions\UpdateException;
use SamagTech\Exceptions\GenericException;
use SamagTech\Exceptions\ValidationException;
use SamagTech\Exceptions\ResourceNotFoundException;

/**
 * Classe astratta che implementa i servizi CRUD di default
 *
 * @implements Service
 * @author Alessandro Marotta
 */
abstract class BaseService implements Service {

    /**
     * Array con gli helpers da precaricare
     *
     *
     * @var array<int,string>
     * @access protected
     */
    protected array $helpers = [
        'debug',
        'text',
        'array'
    ];

    /**
     * Array contenente le regole di validazioni.
     *
     * L'array deve contenere 3 chiavi principali : 'generic', 'insert', 'update'
     *
     * 'generic' → Verrà utilizzata sia in create che update
     * 'insert' → Verrà utilizzata solo in create
     * 'update' → Verrà utilizzata solo in update
     *
     * @var array<string,array<string,string>>
     * @access protected
     */
    protected array $validationsRules = [];

    /**
     * Array contenente i messaggi di validazioni custom
     *
     * L'array deve contenere TIPO_VALIDAZIONE -> MESSAGGIO
     *
     * @var array<string,array<string,string>>
     * @access protected
     */
    protected array $validationsCustomMessage = [];

    /**
     * Stringa contentente il nome del modello.
     *
     * @var string
     * @access protected
     * Default ''
     */
    protected ?string $modelName = null;

    /**
     * Modello principale
     *
     * @var \SamagTech\Core\BaseModel|null
     * @access protected
     */
    protected ?BaseModel $model = null;

    /**
     * Lista contentente la clausola per la select
     *
     * @var array<int,string>
     * @access protected
     * Default []
     *
     * Es. [ 'id', 'name', 'E.name as external_name' ]
     */
    protected array $retrieveSelect = [];

    /**
     * Lista contenente le clausole per i join
     *
     * @var array<int,array<int,string>>|null
     * @access protected
     * Default null
     *
     * Es. [ 'table alias', 'condition', 'left(optional)' ]
     */
    protected ?array $retrieveJoin = null;

    /**
     * Lista contenente le clausole where di base
     *
     * @var array<string,string>|null
     * @access protected
     * Default null
     *
     * Es. [ 'key' => 'value', 'key1 <' => 'value']
     */
    protected ?array $retrieveWhere = null;

    /**
     * Lista contenente le clausole group by di base
     *
     * @var array<int,string>|null
     * @access protected
     * Default null
     *
     * Es. ['name', 'fiscal_code' ]
     */
    protected ?array $retrieveGroupBy = null;

    /**
     * Limite di risorse da recuperare per pagina.
     *
     * Se è settato il flag noPagination a true non viene considerato.
     *
     * @var int
     * @access protected
     * Default 25
     */
    protected int $retriveLimit = 25;

    /**
     * Flag per attivare/disabilitare la paginazione. ( Utile principalmente per i report)
     *
     * @var bool
     * @access protected
     * Default FALSE
     */
    protected bool $noPagination = false;

    /**
     * Clausola per l'ordinamento di default
     *
     * @var array<int,string>
     *
     * @access protected
     *
     * Es. ['id:desc'] OR ['name:asc', 'id:desc']
     *
     * Default 'id:desc'
     */
    protected array $retrieveSortBy = ['id:desc'];

    /**
     * Mappa che deve contentenere la codifica dei campi che possono servire
     * per ordinamento e filtri nelle tabelle joinate
     *
     * L'array deve essere formato con  FILTRO → CAMPO_CON_ALIAS
     *
     *
     * @var array<string,string>
     * @access protected
     * Default []
     *
     * Es. [
     *  'external_name' => 'E.name',
     *  'external_field' => 'T.field'
     * ]
     */
    protected array $joinFieldsFilters = [];

    /**
     * Mappa che deve contentenere la codifica dei campi che possono servire
     * per ordinamento nelle tabelle joinate
     *
     * L'array deve essere formato con  ORDINAMENTO → CAMPO_CON_ALIAS
     *
     * Es. employee_name => E.name dove E è l'alias della tabella
     *
     * @var array<string,string>
     * @access protected
     * Default []
     *
     *  Es. [
     *  'external_name' => 'E.name',
     *  'external_field' => 'T.field'
     * ]
     */
    protected array $joinFieldsSort = [];

    /**
     * Path dove vengono salvate le esportazioni Excel
     *
     * @var string
     * @access protected
     *
     * Default WRITEPATH.'export/'
     */
    protected string $exportPath = 'export/';

    /**
     * Nome di default del file Excel generato durante l'esportazione
     *
     * @var string|null
     * @access protected
     *
     */
    protected ?string $exportFilename = null;

    /**
     * Lista dei campi a cui deve essere ignorata
     * la formattazione
     *
     * @var array|null
     * @access protected
     *
     */
    protected ?array $exportIgnoreFieldsFormat = null;

    /**
     * Lista dell'intestazione delle colonne del file
     *
     * L'ordine è importante per essere allineato con i valori
     *
     * @var array
     * @access protected
     *
     * Default []
     */
    protected array $exportHeader = [];

    /**
     * Lista delle definizioni delle colonne
     *
     * @var array
     * @access protected
     */
    protected array $exportColumnDefinition = [];

    /**
     * Lista delle colonna da eliminare durante l'esportazione Excel
     *
     * @var array
     * @access protected
     *
     * Default ['id']
     */
    protected array $exportDeleteColumns = ['id'];

    /**
     * Configurazione Applicazione
     *
     * @var \SamagTech\Crud\Config\BaseAppConfig
     * @access protected
     */
    protected BaseAppConfig $appConfig;

    /**
     * Dati utente loggato
     *
     * @var object
     * @access protected
     */
    protected ?object $currentUser;

    /**
     * Logger dell'applicativo
     *
     * @var Psr\Log\LoggerInterface
     * @access protected
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Flag per l'attivazione del logger
     * nel singolo CRUD
     *
     * @var bool
     * @access protected
     */
    protected bool $activeCrudLogger = true;

    /**
     * Indica se in questo servizio vengono
     * utilizzati gli array oppure le entità
     *
     * @var bool
     * @access protected
     *
     * Default False
     */
    protected bool $useEntity = false;

    /**
     * Indica l'entità che verrà utilizzata
     * nel caso il modello restituisca l'entità
     *
     * @var CodeIgniter\Entity\Entity|null
     *
     * @access protected
     *
     */
    protected ?Entity $entity = null;

    //---------------------------------------------------------------------------------

    /**
     *  Costruttore.
     *
     * @param Psr\Log\LoggerInterface|null $logger  Logger dell'applicazione
     *
     */
    public function __construct(?LoggerInterface $logger = null) {

        // Carico tutti gli helpers necessari
        helper($this->helpers);

        $this->logger = $logger;

        $this->appConfig = config('Application');

        // Controllo se validation sia una array
        if ( ! is_array($this->validationsRules) ) {
            die ('Le regole di validazione devono essere contenute in un array');
        }

        // Se il modello non è settato allora setto il modello di default
        if ( is_null($this->modelName) ) {
            die('Il modello non è settato');
        }
        else {
            $this->model = new $this->modelName;
        }

        // Setto i dati dell'utente loggato
        $this->currentUser = CurrentUser::getIstance()->getProperty();

        // Imposta l'entità
        $modelReturnType = $this->model->getReturnType();

        if ( ! in_array($modelReturnType, ['array', 'object']) ) {
            $this->entity = $modelReturnType;
            $this->useEntity = true;
        }

        // Inizializzo la libreria di log
        // $this->logger = new Log($this->model->getTable(), $this, CurrentUser::getIstance() ?? null);

    }

    //---------------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     */
    public function create(IncomingRequest $request) : Entity|array {

        // Recupero i dati dalla richiesta
        $data = $request->getJSON(TRUE);

        // Eseguo il check della validazione
        $this->checkValidation($data,'insert');

        // Callback per estrarre dati esterni alla riga
        $extraInsert = $this->getExtraData($data);

        // Se il tipo di ritorno non è un array allora è un entità
        if ( $this->useEntity ) {
            $data = (new $this->entity)->fill($data);
        }

        // Callback pre-inserimento
        $data = $this->preInsertCallback($data);

        // Inizializzo la transazione
        $this->model->transStart();

        // Se è impostato la colonna created_by aggiungo l'utente corrente
        if ( $this->model->useCreatedBy ) {

            if ( $this->useEntity ) {
                $data->created_by = $this->currentUser->id;
            }
            else {
                $data['created_by'] = $this->currentUser->id;
            }

        }

        // Se l'identificativo deve essere una stringa lo calcolo
        if ( $this->model->isStringId () ) {

            $id = $this->model->getNewStringId();

            if ( $this->useEntity ) {
                $data->id = $id;
            }
            else {
                $data['id'] = $id;
            }
        }

        // Inserisco i dati
        $id = $this->model->insert($data);

        // $this->logger->create('create', $id, $data);

        // Callback post-inserimento
        $this->postInsertCallback($id,$data,$extraInsert);

        // Termino la transazione
        $this->model->transComplete();

        // Se la transazione è fallita sollevo un eccezione
        if ( $this->db->transStatus() === FALSE ) {
            throw new CreateException();
        }

        return $this->model->find($id);
    }

    //--------------------------------------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     */
    public function retrieve( IncomingRequest $request) : array {

        // Recupero i parametri da eseguire
        $params = $request->getGet();

        // Creo l'array con la configurazione per la query
        $options = [
            'select'    =>  $this->retrieveSelect,
            'where'     =>  $this->retrieveWhere,
            'join'      =>  $this->retrieveJoin,
            'group_by'  =>  $this->retrieveGroupBy,
            'limit'     =>  ! isset($params['for_page']) ? $this->retriveLimit : $params['for_page'],
            'offset'    =>  ! isset($params['page']) ? 1 : $params['page'],
            'sort_by'   =>  ! isset($params['sort_by']) ? $this->retrieveSortBy : [$params['sort_by']],
            'no_pagination' => $this->noPagination
        ];

        // Elimino dai parametri i dati già recuperati
        unset($params['sort_by'], $params['page'], $params['for_page']);

        // Risposta per la lista
        $data = [
            'page'          => $options['offset'],
            'total_rows'    => 0,
            'rows'          => []
        ];

        // Aggiunge dati alla query e modifica le opzioni
        $options = $this->preRetrieveCallback($options, $params);

        // Recupera i dati in base ai parametri
        $data['rows'] = $this->model->getWithParams($options, $params, $this->joinFieldsFilters, $this->joinFieldsSort);

        // Restutuisce tutte le righe trovate non paginate
        $data['total_rows'] = (int)$this->model->getFoundRows();

        // Callback per la modifica della lista
        $data = $this->postRetrieveCallback($data);

        return $data;
    }

    //--------------------------------------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     */
    public function retrieveById(int|string $id): Entity|array {

        $options = [
            'select'    => $this->retrieveSelect,
            'join'      => $this->retrieveJoin,
        ];

        // Callback per la modifica delle opzioni
        $options = $this->preRetrieveByIdCallback($options);

        // Recupero i dati della risorsa
        $resource =  $this->model->getWithId($id, $options['select'], $options['join']);

        // Se la risorsa non è stata trovata sollevo un eccezione
        if ( is_null($resource) ) {
            throw new ResourceNotFoundException();
        }

        // Callback post recupero dati
        $resource = $this->postRetrieveByIdCallback($resource);

        return $resource;

    }

    //--------------------------------------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     */
    public function update(IncomingRequest $request, int|string $id) : bool {

        $currentData = $this->model->find($id);

        // Controllo se la risorsa esiste
        if  ( is_null($currentData) ) {
            throw new ResourceNotFoundException();
        }

        // Recupero i dati dalla richiesta
        $data = $request->getJSON(TRUE);

        // Eseguo il check della validazione
        $this->checkValidation($data,'update');

        // Callback per estrarre dati esterni alla riga
        $extraUpdate = $this->getExtraData($data);

        if ( $this->useEntity ) {
            $data = $currentData->fill($data);
        }

        // Callback pre-modifica
        $data = $this->preUpdateCallback($id, $data);

        // Inizializzo la transazione
        $this->model->transStart();

        // Se è impostato la colonna updated_by aggiungo l'utente corrente
        if ( $this->model->useUpdatedBy ) {

            if ( $this->useEntity ) {
                $data->created_by = $this->currentUser->id;
            }
            else {
                $data['created_by'] = $this->currentUser->id;
            }

        }

        if ( $this->useEntity ) {
            $data->updated_date = Time::now();
        }
        else {
            $data['updated_date'] = Time::now();
        }

        // Inserisco i dati
        $updated = $this->useEntity ? $this->model->save($data) : $this->model->update($id,$data);

        // Check per il logger
        // $this->logger->create('update', $id, $oldData, $data);

        // Callback post-modifica
        $this->postUpdateCallback($id, $data, $extraUpdate);

        $this->model->transComplete();

        // Se la transazione è fallita sollevo un eccezione
        if ( $this->model->transStatus() === FALSE ) {
            throw new UpdateException();
        }

        return $updated;
    }

    //--------------------------------------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     */
    public function delete(IncomingRequest $request, int|string $id) : bool {

        // Controllo se la risorsa esiste
        if  ( is_null($oldData = $this->model->find($id)) ) {
            throw new ResourceNotFoundException();
        }

        // Inizializzo la transazione
        $this->model->transStart();

        // Funzione pre-cancellazione
        $this->preDeleteCallback($id, $oldData);

        // Cancello la riga e controllo se la funzione restituisce false
        $deleted = $this->model->delete($id) != false;

        // Check per il logger
        // $this->logger->create('delete', $id, $oldData);

        // Callback per ulteriori azioni post cancellazione
        $this->postDeleteCallback($id, $oldData);

        $this->model->transComplete();

        // Se la transazione è fallita sollevo un eccezione
        if ( $this->model->transStatus() === FALSE ) {
            throw new DeleteException();
        }

        return $deleted;
    }

    //------------------------------------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     */
    public function export(IncomingRequest $request) : string {

        // Elimino la paginazione dal listaggio
        $this->noPagination = true;

        // Lancia la funzione di listaggio
        $data = $this->retrieve($request);

        if ( empty ($data['rows']) ) {
            throw new GenericException('Non ci sono dati da esportare');
        }

        $data = $data['rows'];

        $data = $this->useEntity ? $data->toArray() : $data;

        // Eseguo delle operazioni sui dati pre-esportazione
        $this->preExportCallback($data);

        // Creo l'istanza del generatore dell'excel
        $writer = (new ExcelFactory)->createWriter(WRITEPATH. $this->exportPath, $this->exportFilename, $this->exportIgnoreFieldsFormat);

        $writer->setHeader($this->exportHeader);

        if ( ! empty($this->exportColumnDefinition) ) {
            $writer->setColumnDefinition($this->exportColumnDefinition);
        }

        // Cancella le colonne inutili
        $this->deleteUnusedColumns($data);

        // Callback per modifiche pre-build dell'excel
        $writer = $this->preBuildExcelCallback($writer, $data);

        // Setto i dati
        $writer->setBody($data);

        $explodePath = explode('/', $writer->build());

        return $this->exportPath. end($explodePath);

    }

    //------------------------------------------------------------------------------------------------------

    /**
     * Funzione per il recupero di dati extra in caso di create/update
     *
     * @param array $data   Dati delle richiesta
     *
     * @return array
     */
    public function getExtraData(array &$data ) {

        $extra = [];

        // Ciclo l'array data per trovare sottoarray,
        // se ci sono li estraggo
        foreach ( $data as $key => $value ) {

            if ( is_array($value) ) {
                $extra[$key] = $value;
                unset($data[$key]);
            }
        }

        return $extra;
    }

    //------------------------------------------------------------------------------------------------------

    /**
     * Funzione che gestisce la validazione sia in insert che in update.
     *
     * @param array<string,mixed>   $data      Array contenente i dati della richiesta da validare
     * @param string                $action    Azione che si sta compiendo Default ('insert')
     *
     * @throws SamagTech\Exceptions\ValidationException Solleva quest'eccezione se fallisce la validazione
     *
     * @return void
     */
    protected function checkValidation(array $data, string $action = 'insert') {

        // Recupero le regole di validazioni generiche
        $generics = isset($this->validationsRules['generic']) ? $this->validationsRules['generic'] : [];

        // Eseguo il merge delle regole di validazioni
        $rules = isset($this->validationsRules[$action])
            ? $generics + $this->validationsRules[$action]
            : $generics;

        // Se non esistono regole di validazione allora la salto
        if ( ! empty($rules)) {

            $validation = \Config\Services::validation();

            // Vengono settate le regole di validazione, con i messaggi di default
            $validation->setRules($rules,$this->validationsCustomMessage);

            // Lancio la validazione, se fallisce lancio un eccezione
            if ( ! $validation->run($data)) {
                throw new ValidationException($validation->getErrors());
            }
        }
    }

    //----------------------------------------------------------------------------------------------------

    /**
     * Callback eseguita prima dell'inserimento
     *
     * @param Entity|array<string,mixed> $data  Contiene i dati da inserire
     *
     * @return Entity|array<string,mixed>
     */
    protected function preInsertCallback(Entity|array $data) : Entity|array  {
        return $data;
    }

    //----------------------------------------------------------------------------------------------------


    /**
     * Callback eseguita post inserimento dei dati
     *
     * @param int|string                 $id         Identificativo della riga inserita
     * @param Entity|array<string,mixed> $data       Contiene i dati inseriti
     * @param array<string,mixed>        $extraData  Array che contiene i dati extra se ci sono( Default = [])
     *
     * @return void
     */
    protected function postInsertCallback(int|string $id, Entity|array $data, array $extraData = []) : void  {}

    //----------------------------------------------------------------------------------------------------


    /**
     * Callback eseguita pre modifica dei dati
     *
     * @param int|string                 $id     Identificativo della riga da modificare
     * @param Entity|array<string,mixed> $data   Contiene i dati per la modifica
     *
     * @return Entity|array<string,mixed>
     */
    protected function preUpdateCallback(int|string $id, Entity|array $data ) : Entity|array  {
        return $data;
    }

    //----------------------------------------------------------------------------------------------------

    /**
     * Callback eseguita post modifica dei dati
     *
     * @param int|string                  $id         Identificativo della riga inserita
     * @param Entity|array<string,mixed>  $data       Contiene i dati modificati
     * @param array<string,mixed>         $extraData  Array che contiene i dati extra se ci sono( Default = [])
     *
     * @return void
     */
    protected function postUpdateCallback(int|string $id, Entity|array $data, array $extraData = []) : void  {}

    //----------------------------------------------------------------------------------------------------

    /**
     * Callback eseguita pre cancellazione dei dati
     *
     * @param int|string                    $id     Identificativo della riga da cancellare
     * @param Entity|array<string,mixed>    $data   Contiene i dati c
     *
     * @return void
     */
    protected function preDeleteCallback(int|string $id, Entity|array $data ) : void  {}

    //----------------------------------------------------------------------------------------------------

    /**
     * Callback eseguita post cancellazione dei dati
     *
     * @param int|string                 $id     Identificativo risorsa cancellata
     * @param Entity|array<string,mixed> $data   Dati della risorsa cancellata
     *
     * @return void
     */
    protected function postDeleteCallback( int $id, array $data ) : void  {}

    //---------------------------------------------------------------------------------------------------

    /**
     * Callback per effettuare aggiunte o modifiche alle query
     *
     * @param array $options    Array con le opzioni per le query
     * @param array &$params    Array contentente i parametri per la lista
     *
     * @return array    Opzioni modificate
     */
    protected function preRetrieveCallback(array $options, array &$params ) : array {
        return $options;
    }

    //---------------------------------------------------------------------------------------------------

    /**
     * Callback per la gestione della lista post-query
     *
     * @param array<string,array<string,mixed|Entity>> $data               Lista dei dati estratti
     *
     * @return array<string,array<string,mixed|Entity>
     */
    protected function postRetrieveCallback(array $data) : array  {
        return $data;
    }

    //---------------------------------------------------------------------------------------------------

    /**
     * Callback per la gestione delle opzioni pre recupero dati
     *
     * @param array<string,mixed> $options    Opzioni per il recupero dei dati
     *
     * @return array<string,mixed>
     *
     */
    protected function preRetrieveByIdCallback(array $options) : array {
        return $options;
    }

    //---------------------------------------------------------------------------------------------------

    /**
     * Callback per la gestione delle opzioni post recupero dati
     *
     * @param Entity|array<string,mixed> $data    Dati recuperati
     *
     * @return Entity|array<string,mixed>
     *
     */
    protected function postRetrieveByIdCallback(Entity|array $data) : Entity|array {
        return $data;
    }

    //---------------------------------------------------------------------------------------------------


    /**
     * Callback per la modifica dei dati pre-esportazione excel
     *
     * @param array<int,array<string,mixed>|Entity[] &$data   Dati recuperati dalla lista
     *
     * @return void
     */
    protected function preExportCallback(array &$data) : void {}

    //---------------------------------------------------------------------------------------------------

    /**
     * Callback per la gestione dei dati e dell'istanza del generatore
     * prima di lanciare la build dell'excel
     *
     * @param \SamagTech\ExcelLib\Writer              $writer    Istanza generatore Excel
     * @param array<int,array<string,mixed>|Entity[]  $data      Dati da esportare
     *
     * @return SamagTech\ExcelLib\Writer   Ritorna l'istanza modificata
     *
     */
    protected function preBuildExcelCallback (Writer $writer, array &$data ) : Writer {
        return $writer;
    }

    //---------------------------------------------------------------------------------------------------

    /**
     * Elimina le colonne inutili per l'esportazione excel
     *
     * @param array &$data  Dati da esportare
     *
     * @return void
     */
    protected function deleteUnusedColumns(array &$data) : void {

        // Se l'array delle colonne da cancellare è vuoto non faccio nulla
        if ( empty ($this->exportDeleteColumns) ) return;

        // Per ogni riga elimino le colonne da cancellare
        foreach ( $data as &$d ) {

            foreach ( $this->exportDeleteColumns as $edc ) {
                unset($d[$edc]);
            }
        }
    }

    //---------------------------------------------------------------------------------------------------

    /**
     * Restituisce TRUE se il logger è attiva, FALSE altrimenti
     *
     * @return bool
     */
    public function isActiveLogger() : bool {
        return $this->activeCrudLogger;
    }

    //---------------------------------------------------------------------------------------------------
}
