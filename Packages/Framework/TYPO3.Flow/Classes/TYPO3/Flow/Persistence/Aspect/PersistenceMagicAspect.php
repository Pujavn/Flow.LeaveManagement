<?php
namespace TYPO3\Flow\Persistence\Aspect;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\ORM\Mapping as ORM;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Utility\Algorithms;

/**
 * Adds the aspect of persistence magic to relevant objects
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 * @Flow\Introduce("TYPO3\Flow\Persistence\Aspect\PersistenceMagicAspect->isEntityOrValueObject", interfaceName="TYPO3\Flow\Persistence\Aspect\PersistenceMagicInterface")
 */
class PersistenceMagicAspect
{
    /**
     * If the extension "igbinary" is installed, use it for increased performance
     *
     * @var boolean
     */
    protected $useIgBinary;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Pointcut("classAnnotatedWith(TYPO3\Flow\Annotations\Entity) || classAnnotatedWith(Doctrine\ORM\Mapping\Entity)")
     */
    public function isEntity()
    {
    }

    /**
     * @Flow\Pointcut("TYPO3\Flow\Persistence\Aspect\PersistenceMagicAspect->isEntity || classAnnotatedWith(TYPO3\Flow\Annotations\ValueObject)")
     */
    public function isEntityOrValueObject()
    {
    }

    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(length=40)
     * @Flow\Introduce("TYPO3\Flow\Persistence\Aspect\PersistenceMagicAspect->isEntityOrValueObject && filter(TYPO3\Flow\Persistence\Doctrine\Mapping\Driver\FlowAnnotationDriver)")
     */
    protected $Persistence_Object_Identifier;

    /**
     * Initializes this aspect
     *
     * @return void
     */
    public function initializeObject()
    {
        $this->useIgBinary = extension_loaded('igbinary');
    }

    /**
     * After returning advice, making sure we have an UUID for each and every entity.
     *
     * @param JoinPointInterface $joinPoint The current join point
     * @return void
     * @Flow\Before("TYPO3\Flow\Persistence\Aspect\PersistenceMagicAspect->isEntity && method(.*->(__construct|__clone)()) && filter(TYPO3\Flow\Persistence\Doctrine\Mapping\Driver\FlowAnnotationDriver)")
     */
    public function generateUuid(JoinPointInterface $joinPoint)
    {
        /** @var $proxy PersistenceMagicInterface */
        $proxy = $joinPoint->getProxy();
        ObjectAccess::setProperty($proxy, 'Persistence_Object_Identifier', Algorithms::generateUUID(), true);
        $this->persistenceManager->registerNewObject($proxy);
    }

    /**
     * After returning advice, generates the value hash for the object
     *
     * @param JoinPointInterface $joinPoint The current join point
     * @return void
     * @Flow\After("classAnnotatedWith(TYPO3\Flow\Annotations\ValueObject) && method(.*->__construct()) && filter(TYPO3\Flow\Persistence\Doctrine\Mapping\Driver\FlowAnnotationDriver)")
     */
    public function generateValueHash(JoinPointInterface $joinPoint)
    {
        $proxy = $joinPoint->getProxy();
        $proxyClassName = get_class($proxy);
        $hashSourceParts = [];

        $classSchema = $this->reflectionService->getClassSchema($proxyClassName);
        foreach ($classSchema->getProperties() as $property => $propertySchema) {
            // Currently, private properties are transient. Should this behaviour change, they need to be included
            // in the value hash generation
            if ($classSchema->isPropertyTransient($property)
                || $this->reflectionService->isPropertyPrivate($proxyClassName, $property)) {
                continue;
            }

            $propertyValue = ObjectAccess::getProperty($proxy, $property, true);

            if (is_object($propertyValue) === true) {
                // The persistence manager will return NULL if the given object is unknown to persistence
                $propertyValue = ($this->persistenceManager->getIdentifierByObject($propertyValue)) ?: $propertyValue;
            }

            $hashSourceParts[$property] = $propertyValue;
        }

        ksort($hashSourceParts);

        $hashSourceParts['__class_name__'] = $proxyClassName;
        $serializedSource = ($this->useIgBinary === true) ? igbinary_serialize($hashSourceParts) : serialize($hashSourceParts);

        $proxy = $joinPoint->getProxy();
        ObjectAccess::setProperty($proxy, 'Persistence_Object_Identifier', sha1($serializedSource), true);
    }

    /**
     * Mark object as cloned after cloning.
     *
     * Note: this is not used by anything in the Flow base distribution,
     * but might be needed by custom backends (like TYPO3.CouchDB).
     *
     * @param JoinPointInterface $joinPoint
     * @return void
     * @Flow\AfterReturning("TYPO3\Flow\Persistence\Aspect\PersistenceMagicAspect->isEntityOrValueObject && method(.*->__clone())")
     */
    public function cloneObject(JoinPointInterface $joinPoint)
    {
        $joinPoint->getProxy()->Flow_Persistence_clone = true;
    }
}
