<?php

namespace Coshi\MediaBundle\Manager;

use Coshi\MediaBundle\Entity\Media;
use Coshi\MediaBundle\Model\MediaAttachableInteface;
use Coshi\MediaBundle\Model\MediaLinkInteface;
use Coshi\MediaBundle\Model\MediaInterface;
use Coshi\MediaBundle\MediaEvents;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;


class MediaManager
{

    /**
     * class
     *
     * @var mixed
     * @access protected
     */
    protected $class;

    /**
     * kernel
     *
     * @var HttpKernelInterface
     * @access protected
     */
    protected $kernel;

    /**
     * container
     *
     * @var mixed
     * @access protected
     */
    protected $container;

    /**
     * em
     *
     * @var mixed
     * @access protected
     */
    protected $em ;

    /**
     * repository
     *
     * @var EntityRepository
     * @access protected
     */
    protected $repository;

    /**
     * Imager Service instance
     *
     * @var Imager
     * @access protected
     */
    protected $imagerService;


    /**
     * options
     *
     * @var array
     * @access protected
     */
    protected $options;


    /**
     * __construct
     *
     * @param EntityManager $em
     * @param Imager $imager
     * @access public
     * @return void
     */
    public function __construct(
        EntityManager $em,
        $options = null
    )
    {
        $this->entityManager = $em;
        $this->options = $options;

        $this->class = $options['media_class'];

        $this->repository = $this
            ->entityManager
            ->getRepository($this->class)
        ;

    }

    /**
     * getClassInstance
     *
     * @access public
     * @return void
     */
    public function getClassInstance()
    {
        $class = $this->class;
        return new $class();
    }

    /**
     * create
     *
     * @param bool $entity
     * @param bool $withFlush
     * @access public
     * @return void
     */
    public function create(UploadedFile $file, $entity = null, $withFlush = true)
    {
        if (!$entity instanceof MediaInterface) {
            $entity = $this->getClassInstance();
        }

        if (null !== $file) {
            $entity = $this->upload($file, $entity);
        }

        $this->entityManager->persist($entity);
        if ($withFlush) {
            $this->entityManager->flush();
        }
        MediaEvents::dispatchCreate(
            $this->container->get('event_dispatcher'),
            $entity
        );

        return $entity;

    }

    public function update(
        UploadedFile $file,
        MediaInterface $entity,
        $withFlush = true
    )
    {

        if (null !== $file) {
            $entity = $this->upload($file, $entity);
        }

        $this->entityManager->persist($entity);

        if ($withFlush) {
            $this->entityManager->flush();
        }
        MediaEvents::dispatchUpdate(
            $this->container->get('event_dispatcher'),
            $entity
        );
        return $entity;
    }




    /**
     *
     */
    public function attach(
        $object,
        MediaInterface $medium
    )
    {
        $linkObj = $object->getMediaLink();
        $linkObj->setObject($object);
        $linkObj->setMedium($medium);

        $this->entityManager->persist($linkObj);
        $this->entityManager->flush();

        return $linkObj;

    }


    public function upload(UploadedFile $uploadedfile, MediaInterface $entity)
    {

        $file = $uploadedfile->getClientOriginalName();

        $entity->setMimetype($uploadedfile->getMimeType());
        $entity->setSize($uploadedfile->getClientSize());

        $ext = $uploadedfile->guessExtension() ?
            $uploadedfile->guessExtension() : 'bin';

        $entity->setType(Media::UPLOADED_FILE);

        $entity->setOriginal(
            $uploadedfile->getClientOriginalName()
        );

        $entity->setFilename(
            md5(
                rand(1, 9999999).
                time().
                $uploadedfile->getClientOriginalName()
            )
            .'.'.$ext
        );

        $entity->setPath($this->getUploadRootDir());

        $uploadedfile->move(
            $this->getUploadRootDir(),
            $entity->getFilename()
        );

        return $entity;


    }

    public function delete(MediaInterface $entity,$withFlush=true)
    {
        // unlink file
        if (!unlink($entity->getPath().'/'.$entity->getFilename())) {
            throw new RuntimeException('Cannot delete file');
        }

        MediaEvents::dispatchDelete(
            $this->container->get('event_dispatcher'),
            $entity
        );
        $this->entityManager->remove($entity);
        if ($withFlush) {
            $this->entityManager->flush();
        }
    }


    public function getUploadDir()
    {
        return $this->options['uploader']['media_path'];
    }

    public function getUploadRootDir()
    {
        $basepath = $this->kernel->getRootDir().
            '/../'.
            $this->options['uploader']['www_root'].
            '/'.
            $this->options['uploader']['media_path'];
        return $basepath;
    }

    /**
     * getClass
     *
     * @access public
     * @return void
     */
    public function getClass()
    {
        return $this->class;
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getRepository()
    {
        return $this->repository;
    }

}
