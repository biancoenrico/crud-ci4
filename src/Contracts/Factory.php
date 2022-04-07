<?php namespace SamagTech\Contracts;

/**
 * Definizione di un interfaccia per l'applicazione
 * del pattern Factory per la creazione dinamica
 * del servizio
 *
 * @interface
 *
 * @author Alessandro Marotta <alessandro.marotta@samag.tech>
 */
interface Factory {

    /**
     * Restituisce l'istanza del Service in base al token.
     *
     * @param string|null $token    Token che identifica quale servizio istanziare
     *
     * @throws \SamagTech\Exceptions\BadFactoryException  Solleva quest'eccezione se non esiste un servizio di default se il token è null
     *
     * @return \SamagTech\Contracts\Service Restituisce una classe CRUDService
     */
    public function makeService( ?string $token = null) : Service;

}